<?php

use App\Modules\Support\ModuleAutoloader;
use App\Modules\Support\ModuleManifest;
use Tests\Support\TestEnvironmentRestorer;

require __DIR__.'/../vendor/autoload.php';

$moduleManifests = collect(glob(dirname(__DIR__).'/modules/*/module.json') ?: [])
    ->map(function (string $manifestPath): ?ModuleManifest {
        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($decoded) || ! isset($decoded['name'], $decoded['namespace'], $decoded['provider'])) {
            return null;
        }

        $basePath = dirname($manifestPath);
        $namespace = rtrim((string) $decoded['namespace'], '\\').'\\';

        return new ModuleManifest(
            name: (string) $decoded['name'],
            slug: (string) ($decoded['slug'] ?? strtolower((string) $decoded['name'])),
            version: (string) ($decoded['version'] ?? '1.0.0'),
            description: (string) ($decoded['description'] ?? ''),
            author: isset($decoded['author']) ? (string) $decoded['author'] : null,
            homepage: isset($decoded['homepage']) ? (string) $decoded['homepage'] : null,
            icon: isset($decoded['icon']) ? (string) $decoded['icon'] : null,
            namespace: $namespace,
            provider: (string) $decoded['provider'],
            basePath: $basePath,
            appPath: $basePath.'/app',
            pageRootPath: $basePath.'/resources/js/pages',
            routeFiles: [],
            abilitiesPath: null,
            navigationPath: null,
            databaseSeederClass: $namespace.'Database\\Seeders\\DatabaseSeeder',
            enabled: true,
        );
    })
    ->filter()
    ->values();

ModuleAutoloader::register($moduleManifests->all());

TestEnvironmentRestorer::register();
