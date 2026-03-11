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
        if ($this->modules !== null) {
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
            return $this->normalizeModuleIdentifier($manifest->slug) === $normalizedModule
                || $this->normalizeModuleIdentifier($manifest->name) === $normalizedModule;
        });
    }

    /**
     * @return array{items: array<int, array{name: string, slug: string, version: string, description: string, inertiaNamespace: string, url: string}>}
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
     * @return Collection<int, array{name: string, slug: string, version: string, description: string, inertiaNamespace: string, url: string, status: string, enabled: bool}>
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
                    ...$module->toSharedArray(),
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

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($this->files->get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("The enabled modules manifest at [{$manifestPath}] is not valid JSON.", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The enabled modules manifest at [{$manifestPath}] must decode to an array.");
        }

        return collect($decoded)
            ->filter(function (mixed $status, mixed $module): bool {
                return is_string($module)
                    && trim($module) !== ''
                    && is_string($status)
                    && in_array(strtolower(trim($status)), ['enable', 'enabled', 'disable', 'disabled'], true);
            })
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
        } catch (JsonException $exception) {
            throw new RuntimeException("The module manifest at [{$manifestPath}] is not valid JSON.", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The module manifest at [{$manifestPath}] must decode to an array.");
        }

        $directoryName = basename($modulePath);
        $slug = Str::slug((string) ($decoded['slug'] ?? $directoryName));
        $enabledModules = $this->enabledModuleSlugs();

        $name = trim((string) ($decoded['name'] ?? ''));
        $namespace = trim((string) ($decoded['namespace'] ?? ''));
        $provider = trim((string) ($decoded['provider'] ?? ''));

        if ($name === '' || $namespace === '' || $provider === '') {
            throw new InvalidArgumentException("The module manifest at [{$manifestPath}] must define [name], [namespace], and [provider].");
        }

        return new ModuleManifest(
            name: $name,
            slug: $slug,
            version: (string) ($decoded['version'] ?? '1.0.0'),
            description: (string) ($decoded['description'] ?? ''),
            entryUrl: (string) ($decoded['entry_url'] ?? '/'.$slug),
            namespace: rtrim($namespace, '\\').'\\',
            provider: $provider,
            basePath: $modulePath,
            appPath: $modulePath.'/app',
            enabled: (bool) ($decoded['enabled'] ?? true) && (
                in_array($this->normalizeModuleIdentifier($slug), $enabledModules, true)
                || in_array($this->normalizeModuleIdentifier($name), $enabledModules, true)
            ),
        );
    }
}
