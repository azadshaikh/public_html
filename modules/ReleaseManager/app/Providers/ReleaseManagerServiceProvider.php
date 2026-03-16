<?php

namespace Modules\ReleaseManager\Providers;

use App\Modules\Support\ModuleServiceProvider;

class ReleaseManagerServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'releasemanager';
    }

    public function register(): void
    {
        parent::register();
        $this->app->register(RouteServiceProvider::class);
        $this->registerAllConfigFiles();
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadTranslationsFrom($this->modulePath('lang'), $this->moduleSlug());
        $this->loadJsonTranslationsFrom($this->modulePath('lang'));

        $this->loadViewsFrom($this->modulePath('resources/views'), $this->moduleSlug());

        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
    }

    protected function registerAllConfigFiles(): void
    {
        $configPath = $this->modulePath('config');

        if (is_dir($configPath)) {
            foreach (glob("$configPath/*.php") as $file) {
                // Determine the config key from the filename
                $name = basename($file, '.php');
                if ($name === 'config') {
                    // map 'config.php' to the module slug 'releasemanager'
                    $this->mergeConfigFrom($file, $this->moduleSlug());
                } else {
                    // map 'navigation.php' to 'releasemanager.navigation'
                    $this->mergeConfigFrom($file, $this->moduleSlug().'.'.$name);
                }
            }
        }
    }
}
