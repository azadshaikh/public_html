<?php

namespace App\Modules\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract protected function moduleSlug(): string;

    /**
     * Register module services.
     */
    public function register(): void
    {
        $configPath = $this->modulePath('config/'.$this->moduleSlug().'.php');

        if (is_file($configPath)) {
            $this->mergeConfigFrom($configPath, $this->moduleSlug());
        }
    }

    /**
     * Bootstrap module services.
     */
    public function boot(): void
    {
        $webRoutesPath = $this->modulePath('routes/web.php');
        $viewsPath = $this->modulePath('resources/views');
        $langPath = $this->modulePath('lang');
        $migrationsPath = $this->modulePath('database/migrations');

        if (is_file($webRoutesPath)) {
            Route::middleware(['web', 'module.enabled:'.$this->moduleSlug()])->group($webRoutesPath);
        }

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->moduleSlug());
        }

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleSlug());
            $this->loadJsonTranslationsFrom($langPath);
        }

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    protected function modulePath(string $path = ''): string
    {
        $basePath = dirname((string) (new ReflectionClass(static::class))->getFileName(), 3);

        if ($path === '') {
            return $basePath;
        }

        return $basePath.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
    }
}
