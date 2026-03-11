<?php

namespace App\Plugins\Support;

final readonly class PluginManifest
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public string $description,
        public string $entryUrl,
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

    /**
     * @return array{name: string, slug: string, version: string, description: string, inertiaNamespace: string, url: string}
     */
    public function toSharedArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'version' => $this->version,
            'description' => $this->description,
            'inertiaNamespace' => $this->inertiaNamespace(),
            'url' => $this->entryUrl,
        ];
    }
}
