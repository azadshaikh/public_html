<?php

namespace Tests\Support;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Support\Facades\File;

trait InteractsWithModuleManifest
{
    protected string $moduleManifestPath;

    /**
     * @param  array<string, string>  $statuses
     */
    protected function setUpModuleManifest(string $fileName, array $statuses = []): void
    {
        $this->moduleManifestPath = storage_path('framework/testing/'.$fileName);

        File::ensureDirectoryExists(dirname($this->moduleManifestPath));

        if ($statuses !== []) {
            $this->setModuleStatuses($statuses);
        }
    }

    /**
     * @param  array<string, string>  $statuses
     */
    protected function setModuleStatuses(array $statuses): void
    {
        File::put(
            $this->moduleManifestPath,
            json_encode($statuses, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        config()->set('modules.manifest', $this->moduleManifestPath);
        $this->refreshModuleManager();
    }

    protected function refreshModuleManager(): void
    {
        app()->forgetInstance(ModuleManager::class);
        app()->singleton(ModuleManager::class, fn ($app): ModuleManager => new ModuleManager(
            files: $app['files'],
            config: $app['config'],
        ));

        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());
    }

    protected function tearDownModuleManifest(): void
    {
        if (isset($this->moduleManifestPath) && $this->moduleManifestPath !== '') {
            File::delete($this->moduleManifestPath);
        }
    }
}
