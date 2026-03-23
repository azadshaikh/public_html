<?php

declare(strict_types=1);

namespace Modules\Customers\Providers;

use App\Models\User;
use App\Modules\Support\ModuleServiceProvider;
use Modules\Customers\Contracts\BelongsToCustomer;
use Modules\Customers\Contracts\CustomerAggregator;
use Modules\Customers\Observers\UserCustomerSyncObserver;
use Modules\Customers\Services\CustomerService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CustomersServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'customers';
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerObservers();
    }

    public function register(): void
    {
        parent::register();

        $this->registerAllConfigFiles();

        $this->app->bind(BelongsToCustomer::class, CustomerService::class);
        $this->app->bind(CustomerAggregator::class, CustomerService::class);
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

    private function registerObservers(): void
    {
        if (class_exists(User::class)) {
            User::observe(UserCustomerSyncObserver::class);
        }
    }
}
