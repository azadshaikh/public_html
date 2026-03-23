<?php

declare(strict_types=1);

namespace Modules\Orders\Providers;

use App\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Orders\Console\Commands\ExpireStaleOrders;
use Modules\Orders\Services\OrderScaffoldService;
use Modules\Orders\Services\OrderService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class OrdersServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'orders';
    }

    public function register(): void
    {
        parent::register();
        $this->registerAllConfigFiles();

        $this->app->singleton(OrderService::class);
        $this->app->singleton(OrderScaffoldService::class);
    }

    protected function registerAllConfigFiles(): void
    {
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
            $segments = explode('.', $this->moduleSlug().'.'.$configKey);

            $normalized = [];

            foreach ($segments as $segment) {
                if (end($normalized) !== $segment) {
                    $normalized[] = $segment;
                }
            }

            $key = $relativePath === 'config.php' ? $this->moduleSlug() : implode('.', $normalized);

            if (! $this->app->configurationIsCached()) {
                $existing = config($key, []);
                $moduleConfig = require $file->getPathname();
                config([$key => array_replace_recursive($existing, $moduleConfig)]);
            }
        }
    }

    public function boot(): void
    {
        parent::boot();

        $this->commands([
            ExpireStaleOrders::class,
        ]);

        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('orders:expire-stale')->hourly();
        });
    }
}
