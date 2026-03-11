<?php

namespace App\Plugins;

use App\Plugins\Support\PluginManifest;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class PluginManager
{
    /**
     * @var Collection<int, PluginManifest>|null
     */
    protected ?Collection $plugins = null;

    public function __construct(
        protected Filesystem $files,
        protected Repository $config,
    ) {}

    /**
     * @return Collection<int, PluginManifest>
     */
    public function all(): Collection
    {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $this->plugins = collect($this->pluginDirectories())
            ->map(fn (string $pluginPath): ?PluginManifest => $this->manifestFromPath($pluginPath))
            ->filter()
            ->sortBy(fn (PluginManifest $plugin): string => $plugin->name)
            ->values();

        return $this->plugins;
    }

    /**
     * @return Collection<int, PluginManifest>
     */
    public function enabled(): Collection
    {
        return $this->all()
            ->filter(fn (PluginManifest $plugin): bool => $plugin->enabled)
            ->values();
    }

    /**
     * @return array{items: array<int, array{name: string, slug: string, version: string, description: string, inertiaNamespace: string, url: string}>}
     */
    public function sharedData(): array
    {
        return [
            'items' => $this->enabled()
                ->map(fn (PluginManifest $plugin): array => $plugin->toSharedArray())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array{name: string, slug: string, version: string, description: string, inertiaNamespace: string, url: string, status: string, enabled: bool}>
     */
    public function managementData(): Collection
    {
        $pluginStatuses = $this->pluginStatuses();

        return $this->all()
            ->map(function (PluginManifest $plugin) use ($pluginStatuses): array {
                $status = $pluginStatuses[$plugin->name]
                    ?? $pluginStatuses[$plugin->slug]
                    ?? 'disabled';

                return [
                    ...$plugin->toSharedArray(),
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
        $manifestPath = (string) $this->config->get('plugins.manifest', base_path('plugins.json'));

        $normalizedStatuses = $this->all()
            ->mapWithKeys(function (PluginManifest $plugin) use ($statuses): array {
                $status = $statuses[$plugin->name]
                    ?? $statuses[$plugin->slug]
                    ?? 'disabled';

                return [
                    $plugin->name => $this->normalizePluginStatus($status),
                ];
            })
            ->all();

        $directory = dirname($manifestPath);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $json = json_encode($normalizedStatuses, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $this->files->put($manifestPath, $json.PHP_EOL);
        $this->plugins = null;
    }

    /**
     * @return array<int, string>
     */
    protected function pluginDirectories(): array
    {
        $pluginPath = (string) $this->config->get('plugins.path', base_path('plugins'));

        if (! $this->files->isDirectory($pluginPath)) {
            return [];
        }

        return $this->files->directories($pluginPath);
    }

    /**
     * @return array<int, string>
     */
    protected function enabledPluginSlugs(): array
    {
        $statuses = $this->pluginStatuses();

        return collect($statuses)
            ->filter(fn (string $status): bool => $status === 'enabled')
            ->keys()
            ->map(fn (mixed $plugin): string => $this->normalizePluginIdentifier((string) $plugin))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function pluginStatuses(): array
    {
        $manifestPath = (string) $this->config->get('plugins.manifest', base_path('plugins.json'));

        if (! $this->files->isFile($manifestPath)) {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($this->files->get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("The enabled plugins manifest at [{$manifestPath}] is not valid JSON.", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The enabled plugins manifest at [{$manifestPath}] must decode to an array.");
        }

        return collect($decoded)
            ->filter(function (mixed $status, mixed $plugin): bool {
                return is_string($plugin)
                    && trim($plugin) !== ''
                    && is_string($status)
                    && in_array(strtolower(trim($status)), ['enable', 'enabled', 'disable', 'disabled'], true);
            })
            ->mapWithKeys(fn (string $status, string $plugin): array => [
                $plugin => $this->normalizePluginStatus($status),
            ])
            ->all();
    }

    protected function normalizePluginIdentifier(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $value));
    }

    protected function normalizePluginStatus(string $value): string
    {
        return in_array(strtolower(trim($value)), ['enable', 'enabled'], true)
            ? 'enabled'
            : 'disabled';
    }

    protected function manifestFromPath(string $pluginPath): ?PluginManifest
    {
        $manifestPath = $pluginPath.'/plugin.json';

        if (! $this->files->isFile($manifestPath)) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($this->files->get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("The plugin manifest at [{$manifestPath}] is not valid JSON.", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("The plugin manifest at [{$manifestPath}] must decode to an array.");
        }

        $directoryName = basename($pluginPath);
        $slug = Str::slug((string) ($decoded['slug'] ?? $directoryName));
        $enabledPlugins = $this->enabledPluginSlugs();

        $name = trim((string) ($decoded['name'] ?? ''));
        $namespace = trim((string) ($decoded['namespace'] ?? ''));
        $provider = trim((string) ($decoded['provider'] ?? ''));

        if ($name === '' || $namespace === '' || $provider === '') {
            throw new InvalidArgumentException("The plugin manifest at [{$manifestPath}] must define [name], [namespace], and [provider].");
        }

        return new PluginManifest(
            name: $name,
            slug: $slug,
            version: (string) ($decoded['version'] ?? '1.0.0'),
            description: (string) ($decoded['description'] ?? ''),
            entryUrl: (string) ($decoded['entry_url'] ?? '/'.$slug),
            namespace: rtrim($namespace, '\\').'\\',
            provider: $provider,
            basePath: $pluginPath,
            appPath: $pluginPath.'/app',
            enabled: (bool) ($decoded['enabled'] ?? true) && (
                in_array($this->normalizePluginIdentifier($slug), $enabledPlugins, true)
                || in_array($this->normalizePluginIdentifier($name), $enabledPlugins, true)
            ),
        );
    }
}
