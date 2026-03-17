<?php

namespace App\Modules;

use App\Modules\Support\ModuleManifest;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class ModuleManager
{
    /**
     * @var Collection<int, ModuleManifest>|null
     */
    protected ?Collection $modules = null;

    public function __construct(
        protected Filesystem $files,
        protected Repository $config,
    ) {}

    /**
     * @return Collection<int, ModuleManifest>
     */
    public function all(): Collection
    {
        if ($this->modules instanceof Collection) {
            return $this->modules;
        }

        $this->modules = collect($this->moduleDirectories())
            ->map(fn (string $modulePath): ?ModuleManifest => $this->manifestFromPath($modulePath))
            ->filter()
            ->sortBy(fn (ModuleManifest $module): string => $module->name)
            ->values();

        return $this->modules;
    }

    /**
     * @return Collection<int, ModuleManifest>
     */
    public function enabled(): Collection
    {
        return $this->all()
            ->filter(fn (ModuleManifest $module): bool => $module->enabled)
            ->values();
    }

    public function isEnabled(string $module): bool
    {
        $normalizedModule = $this->normalizeModuleIdentifier($module);

        return $this->enabled()->contains(function (ModuleManifest $manifest) use ($normalizedModule): bool {
            if ($this->normalizeModuleIdentifier($manifest->slug) === $normalizedModule) {
                return true;
            }

            return $this->normalizeModuleIdentifier($manifest->name) === $normalizedModule;
        });
    }

    public function find(string $module): ?ModuleManifest
    {
        $normalizedModule = $this->normalizeModuleIdentifier($module);

        return $this->all()->first(function (ModuleManifest $manifest) use ($normalizedModule): bool {
            if ($this->normalizeModuleIdentifier($manifest->slug) === $normalizedModule) {
                return true;
            }

            return $this->normalizeModuleIdentifier($manifest->name) === $normalizedModule;
        });
    }

    public function findOrFail(string $module): ModuleManifest
    {
        $manifest = $this->find($module);

        if (! $manifest instanceof ModuleManifest) {
            throw new InvalidArgumentException(sprintf('Unable to locate module [%s].', $module));
        }

        return $manifest;
    }

    /**
     * @return array{items: array<int, array{name: string, slug: string, version: string, description: string, author: ?string, homepage: ?string, icon: ?string, inertiaNamespace: string, url: string, provider: string, providerPath: string, pageRootPath: string, routeFiles: array<string, string>, abilitiesPath: ?string, navigationPath: ?string, databaseSeederClass: string, databaseSeederPath: string}>}
     */
    public function sharedData(): array
    {
        return [
            'items' => $this->enabled()
                ->map(fn (ModuleManifest $module): array => $module->toSharedArray())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array{name: string, slug: string, version: string, description: string, author: ?string, homepage: ?string, icon: ?string, inertiaNamespace: string, url: string, provider: string, providerPath: string, pageRootPath: string, routeFiles: array<string, string>, abilitiesPath: ?string, navigationPath: ?string, databaseSeederClass: string, databaseSeederPath: string, status: string, enabled: bool}>
     */
    public function managementData(): Collection
    {
        $moduleStatuses = $this->moduleStatuses();

        return $this->all()
            ->map(function (ModuleManifest $module) use ($moduleStatuses): array {
                $status = $moduleStatuses[$module->name]
                    ?? $moduleStatuses[$module->slug]
                    ?? 'disabled';

                return [
                    ...$module->toManagementArray(),
                    'status' => $status,
                    'enabled' => $status === 'enabled',
                ];
            })
            ->values();
    }

    /**
     * @param  array<string, string>  $statuses
     */
    public function writeStatuses(array $statuses): void
    {
        $manifestPath = (string) $this->config->get('modules.manifest', base_path('modules.json'));

        $normalizedStatuses = $this->all()
            ->mapWithKeys(function (ModuleManifest $module) use ($statuses): array {
                $status = $statuses[$module->name]
                    ?? $statuses[$module->slug]
                    ?? 'disabled';

                return [
                    $module->name => $this->normalizeModuleStatus($status),
                ];
            })
            ->all();

        $directory = dirname($manifestPath);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $json = json_encode($normalizedStatuses, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $this->files->put($manifestPath, $json.PHP_EOL);
        $this->modules = null;
    }

    /**
     * @return array<int, string>
     */
    protected function moduleDirectories(): array
    {
        $modulePath = (string) $this->config->get('modules.path', base_path('modules'));

        if (! $this->files->isDirectory($modulePath)) {
            return [];
        }

        return $this->files->directories($modulePath);
    }

    /**
     * @return array<int, string>
     */
    protected function enabledModuleSlugs(): array
    {
        $statuses = $this->moduleStatuses();

        return collect($statuses)
            ->filter(fn (string $status): bool => $status === 'enabled')
            ->keys()
            ->map(fn (mixed $module): string => $this->normalizeModuleIdentifier((string) $module))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function moduleStatuses(): array
    {
        $manifestPath = (string) $this->config->get('modules.manifest', base_path('modules.json'));

        if (! $this->files->isFile($manifestPath)) {
            return [];
        }

        $manifestContents = trim($this->files->get($manifestPath));

        if ($manifestContents === '') {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('The enabled modules manifest at [%s] is not valid JSON.', $manifestPath), $jsonException->getCode(), previous: $jsonException);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(sprintf('The enabled modules manifest at [%s] must decode to an array.', $manifestPath));
        }

        return collect($decoded)
            ->filter(fn (mixed $status, mixed $module): bool => is_string($module)
                && trim($module) !== ''
                && is_string($status)
                && in_array(strtolower(trim($status)), ['enable', 'enabled', 'disable', 'disabled'], true))
            ->mapWithKeys(fn (string $status, string $module): array => [
                $module => $this->normalizeModuleStatus($status),
            ])
            ->all();
    }

    protected function normalizeModuleIdentifier(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $value));
    }

    protected function normalizeModuleStatus(string $value): string
    {
        return in_array(strtolower(trim($value)), ['enable', 'enabled'], true)
            ? 'enabled'
            : 'disabled';
    }

    protected function manifestFromPath(string $modulePath): ?ModuleManifest
    {
        $manifestPath = $modulePath.'/module.json';

        if (! $this->files->isFile($manifestPath)) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($this->files->get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('The module manifest at [%s] is not valid JSON.', $manifestPath), $jsonException->getCode(), previous: $jsonException);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(sprintf('The module manifest at [%s] must decode to an array.', $manifestPath));
        }

        $directoryName = basename($modulePath);
        $slug = Str::slug((string) ($decoded['slug'] ?? $directoryName));
        $enabledModules = $this->enabledModuleSlugs();

        $name = trim((string) ($decoded['name'] ?? ''));
        $author = trim((string) ($decoded['author'] ?? ''));
        $homepage = trim((string) ($decoded['homepage'] ?? ''));
        $icon = trim((string) ($decoded['icon'] ?? ''));
        $namespace = trim((string) ($decoded['namespace'] ?? ''));
        $provider = trim((string) ($decoded['provider'] ?? ''));
        $normalizedNamespace = rtrim($namespace, '\\').'\\';

        if ($name === '' || $namespace === '' || $provider === '') {
            throw new InvalidArgumentException(sprintf('The module manifest at [%s] must define [name], [namespace], and [provider].', $manifestPath));
        }

        $pageRootPath = $this->resolveModulePath(
            $modulePath,
            $decoded['page_root'] ?? sprintf('resources/js/pages/%s', $slug),
        );

        $routeFiles = $this->resolveRouteFiles($modulePath, $decoded['route_files'] ?? ['web' => 'routes/web.php']);

        $abilitiesPath = $this->nullableResolvedModulePath($modulePath, $decoded['abilities_path'] ?? 'config/abilities.php');
        $navigationPath = $this->nullableResolvedModulePath($modulePath, $decoded['navigation_path'] ?? 'config/navigation.php');
        $databaseSeederClass = trim((string) ($decoded['database_seeder'] ?? ($normalizedNamespace.'Database\\Seeders\\DatabaseSeeder')));

        return new ModuleManifest(
            name: $name,
            slug: $slug,
            version: (string) ($decoded['version'] ?? '1.0.0'),
            description: (string) ($decoded['description'] ?? ''),
            author: $author !== '' ? $author : null,
            homepage: $homepage !== '' ? $homepage : null,
            icon: $icon !== '' ? $icon : null,
            namespace: $normalizedNamespace,
            provider: $provider,
            basePath: $modulePath,
            appPath: $modulePath.'/app',
            pageRootPath: $pageRootPath,
            routeFiles: $routeFiles,
            abilitiesPath: $abilitiesPath,
            navigationPath: $navigationPath,
            databaseSeederClass: $databaseSeederClass,
            enabled: (
                in_array($this->normalizeModuleIdentifier($slug), $enabledModules, true)
                || in_array($this->normalizeModuleIdentifier($name), $enabledModules, true)
            ),
        );
    }

    private function resolveModulePath(string $modulePath, mixed $path): string
    {
        $relativePath = trim((string) $path);

        if ($relativePath === '') {
            return $modulePath;
        }

        return $modulePath.'/'.ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    private function nullableResolvedModulePath(string $modulePath, mixed $path): ?string
    {
        $relativePath = trim((string) $path);

        if ($relativePath === '') {
            return null;
        }

        return $this->resolveModulePath($modulePath, $relativePath);
    }

    /**
     * @return array<string, string>
     */
    private function resolveRouteFiles(string $modulePath, mixed $routeFiles): array
    {
        if (! is_array($routeFiles)) {
            return ['web' => $this->resolveModulePath($modulePath, 'routes/web.php')];
        }

        return collect($routeFiles)
            ->filter(fn (mixed $path, mixed $key): bool => is_string($key) && trim($key) !== '' && is_string($path) && trim($path) !== '')
            ->mapWithKeys(fn (string $path, string $key): array => [
                $key => $this->resolveModulePath($modulePath, $path),
            ])
            ->whenEmpty(fn ($collection) => $collection->put('web', $this->resolveModulePath($modulePath, 'routes/web.php')))
            ->all();
    }
}
