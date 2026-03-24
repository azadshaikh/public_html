<?php

namespace Modules\Agency\Providers;

use App\Modules\Support\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Agency\Console\RepairWebsiteCacheSiteIdsCommand;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AgencyServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'agency';
    }

    public function register(): void
    {
        $this->registerAllConfigFiles();
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerApiRoutes();
        $this->registerCommands();
    }

    protected function registerApiRoutes(): void
    {
        $apiRoutesPath = $this->modulePath('routes/api.php');

        if (! is_file($apiRoutesPath)) {
            return;
        }

        Route::middleware('api')
            ->prefix('api/agency')
            ->name('api.')
            ->group($apiRoutesPath);
    }

    protected function registerCommands(): void
    {
        $commands = [
            RepairWebsiteCacheSiteIdsCommand::class,
        ];

        $this->commands(array_filter($commands, class_exists(...)));
    }

    protected function registerAllConfigFiles(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $configPath = $this->modulePath('config');

        if (! is_dir($configPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $configKey = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
            $key = $configKey === 'agency' ? 'agency' : 'agency.'.$configKey;

            $existing = config($key, []);
            $moduleConfig = require $file->getPathname();

            if (is_array($existing) && is_array($moduleConfig)) {
                config([$key => array_replace_recursive($existing, $moduleConfig)]);
            } else {
                config([$key => $moduleConfig]);
            }
        }
    }
}
