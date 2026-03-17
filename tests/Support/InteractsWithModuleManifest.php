<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Support\Facades\File;

trait InteractsWithModuleManifest
{
    protected string $moduleManifestPath;

    protected ?string $originalModulesPath = null;

    protected ?string $originalModulesManifestPath = null;

    /**
     * @var array<int, string>
     */
    protected array $moduleSandboxPaths = [];

    /**
     * @param  array<string, string>  $statuses
     */
    protected function setUpModuleManifest(string $fileName, array $statuses = []): void
    {
        $this->originalModulesManifestPath ??= (string) config('modules.manifest');
        $this->moduleManifestPath = storage_path('framework/testing/'.$fileName);

        File::ensureDirectoryExists(dirname($this->moduleManifestPath));

        if ($statuses !== []) {
            $this->setModuleStatuses($statuses);
        }
    }

    /**
     * @param  array<int, string>  $modules
     */
    protected function withEnabledModules(array $modules): void
    {
        $this->setModuleStatuses(
            collect($modules)
                ->mapWithKeys(fn (string $module): array => [$module => 'enabled'])
                ->all(),
        );
    }

    /**
     * @param  array<int, string>  $modules
     */
    protected function withDisabledModules(array $modules): void
    {
        $this->setModuleStatuses(
            collect($modules)
                ->mapWithKeys(fn (string $module): array => [$module => 'disabled'])
                ->all(),
        );
    }

    protected function useModuleSandbox(string $directoryName): string
    {
        $this->originalModulesPath ??= (string) config('modules.path');

        $sandboxPath = storage_path('framework/testing/'.$directoryName);

        File::deleteDirectory($sandboxPath);
        File::ensureDirectoryExists($sandboxPath.'/modules');

        $this->moduleSandboxPaths[] = $sandboxPath;

        config()->set('modules.path', $sandboxPath.'/modules');
        $this->refreshModuleManager();

        return $sandboxPath;
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

        foreach ($this->moduleSandboxPaths as $sandboxPath) {
            File::deleteDirectory($sandboxPath);
        }

        if ($this->originalModulesPath !== null) {
            config()->set('modules.path', $this->originalModulesPath);
        }

        if ($this->originalModulesManifestPath !== null) {
            config()->set('modules.manifest', $this->originalModulesManifestPath);
        }

        $this->refreshModuleManager();
    }
}
