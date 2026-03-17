<?php

namespace App\Modules\Support;

final readonly class ModuleManifest
{
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

    /**
     * @return array{name: string, slug: string, version: string, description: string, author: ?string, homepage: ?string, icon: ?string, inertiaNamespace: string, url: string}
     */
    public function toSharedArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'homepage' => $this->homepage,
            'icon' => $this->icon,
            'inertiaNamespace' => $this->inertiaNamespace(),
            'url' => $this->url(),
        ];
    }

    /**
     * @return array{name: string, version: string, description: string, author: ?string, homepage: ?string, icon: ?string}
     */
    public function toManagementArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'homepage' => $this->homepage,
            'icon' => $this->icon,
        ];
    }
}
