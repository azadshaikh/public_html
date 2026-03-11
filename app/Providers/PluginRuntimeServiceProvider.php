<?php

namespace App\Providers;

use App\Plugins\PluginManager;
use App\Plugins\Support\PluginAutoloader;
use Illuminate\Support\ServiceProvider;

class PluginRuntimeServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->app->singleton(PluginManager::class, fn ($app): PluginManager => new PluginManager(
            files: $app['files'],
            config: $app['config'],
        ));

        $pluginManager = $this->app->make(PluginManager::class);

        PluginAutoloader::register($pluginManager->enabled()->all());

        foreach ($pluginManager->enabled() as $plugin) {
            $this->app->register($plugin->provider);
        }
    }
}
