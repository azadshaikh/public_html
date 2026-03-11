<?php

namespace App\Providers;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Support\ServiceProvider;

class ModuleRuntimeServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, fn ($app): ModuleManager => new ModuleManager(
            files: $app['files'],
            config: $app['config'],
        ));

        $moduleManager = $this->app->make(ModuleManager::class);

        ModuleAutoloader::register($moduleManager->enabled()->all());

        foreach ($moduleManager->enabled() as $module) {
            $this->app->register($module->provider);
        }
    }
}
