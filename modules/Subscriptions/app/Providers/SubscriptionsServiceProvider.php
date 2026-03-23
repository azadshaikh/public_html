<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Providers;

use App\Modules\Support\ModuleServiceProvider;
use Modules\Subscriptions\Contracts\SubscriptionAggregator;
use Modules\Subscriptions\Services\SubscriptionService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SubscriptionsServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'subscriptions';
    }

    public function register(): void
    {
        parent::register();

        $this->registerAllConfigFiles();

        // Bind contracts to implementations
        $this->app->bind(SubscriptionAggregator::class, SubscriptionService::class);

        // Singleton services
        $this->app->singleton(SubscriptionService::class);
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
}
