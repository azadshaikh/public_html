<?php

namespace App\Plugins\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class PluginServiceProvider extends ServiceProvider
{
    abstract protected function pluginSlug(): string;

    /**
     * Register plugin services.
     */
    public function register(): void
    {
        $configPath = $this->pluginPath('config/'.$this->pluginSlug().'.php');

        if (is_file($configPath)) {
            $this->mergeConfigFrom($configPath, $this->pluginSlug());
        }
    }

    /**
     * Bootstrap plugin services.
     */
    public function boot(): void
    {
        $webRoutesPath = $this->pluginPath('routes/web.php');
        $viewsPath = $this->pluginPath('resources/views');
        $langPath = $this->pluginPath('lang');
        $migrationsPath = $this->pluginPath('database/migrations');

        if (is_file($webRoutesPath)) {
            Route::middleware('web')->group($webRoutesPath);
        }

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->pluginSlug());
        }

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->pluginSlug());
            $this->loadJsonTranslationsFrom($langPath);
        }

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    protected function pluginPath(string $path = ''): string
    {
        $basePath = dirname((string) (new ReflectionClass(static::class))->getFileName(), 3);

        if ($path === '') {
            return $basePath;
        }

        return $basePath.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
    }
}
