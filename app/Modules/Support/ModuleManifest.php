<?php

declare(strict_types=1);

namespace App\Modules\Support;

final readonly class ModuleManifest
{
    /**
     * @param  array<string, string>  $routeFiles
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public string $description,
        public ?string $author,
        public ?string $homepage,
        public ?string $icon,
        public string $namespace,
        public string $provider,
        public string $basePath,
        public string $appPath,
        public string $pageRootPath,
        public array $routeFiles,
        public ?string $abilitiesPath,
        public ?string $navigationPath,
        public string $databaseSeederClass,
        public bool $enabled,
    ) {}

    public function inertiaNamespace(): string
    {
        return $this->slug.'/';
    }

    public function url(): string
    {
        return '/'.$this->slug;
    }

    public function databasePath(): string
    {
        return $this->basePath.'/database';
    }

    public function databaseFactoriesPath(): string
    {
        return $this->databasePath().'/factories';
    }

    public function databaseSeedersPath(): string
    {
        return $this->databasePath().'/seeders';
    }

    public function providerPath(): string
    {
        return $this->classPath($this->provider, $this->appPath);
    }

    public function databaseSeederPath(): string
    {
        return $this->classPath($this->databaseSeederClass, $this->databaseSeedersPath(), trim($this->namespace, '\\').'\\Database\\Seeders\\');
    }

    public function hasRouteFile(string $key = 'web'): bool
    {
        return array_key_exists($key, $this->routeFiles);
    }

    /**
     * @return array{name: string, slug: string, inertiaNamespace: string}
     */
    public function runtimeMetadata(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'inertiaNamespace' => $this->inertiaNamespace(),
        ];
    }

    /**
     * @return array{name: string, slug: string, version: string, description: string, author: ?string, homepage: ?string, icon: ?string, inertiaNamespace: string, url: string, provider: string, providerPath: string, pageRootPath: string, routeFiles: array<string, string>, abilitiesPath: ?string, navigationPath: ?string, databaseSeederClass: string, databaseSeederPath: string}
     */
    public function managementMetadata(): array
    {
        return [
            ...$this->runtimeMetadata(),
            'provider' => $this->provider,
            'providerPath' => $this->providerPath(),
            'pageRootPath' => $this->pageRootPath,
            'routeFiles' => $this->routeFiles,
            'abilitiesPath' => $this->abilitiesPath,
            'navigationPath' => $this->navigationPath,
            'databaseSeederClass' => $this->databaseSeederClass,
            'databaseSeederPath' => $this->databaseSeederPath(),
        ];
    }

    /**
     * @return array{name: string, slug: string, inertiaNamespace: string}
     */
    public function toSharedArray(): array
    {
        return $this->runtimeMetadata();
    }

    /**
     * @return array{name: string, slug: string, version: string, description: string, author: ?string, homepage: ?string, icon: ?string, provider: string, providerPath: string, pageRootPath: string, routeFiles: array<string, string>, abilitiesPath: ?string, navigationPath: ?string, databaseSeederClass: string, databaseSeederPath: string}
     */
    public function toManagementArray(): array
    {
        return [
            ...$this->managementMetadata(),
        ];
    }

    private function classPath(string $class, string $basePath, ?string $prefix = null): string
    {
        $namespacePrefix = $prefix ?? rtrim($this->namespace, '\\').'\\';
        $relativeClass = str_starts_with($class, $namespacePrefix)
            ? substr($class, strlen($namespacePrefix))
            : $class;

        return rtrim($basePath, '/').'/'.str_replace('\\', '/', $relativeClass).'.php';
    }
}
