<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Providers;

use App\Modules\Support\ModuleServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class HelpdeskServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'helpdesk';
    }

    public function register(): void
    {
        parent::register();

        $this->registerAllConfigFiles();
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

            $key = $relativePath === 'config.php'
                ? $this->moduleSlug()
                : implode('.', $normalized);

            $this->mergeConfigRecursively($file->getPathname(), $key);
        }
    }

    protected function mergeConfigRecursively(string $path, string $key): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $existing = config($key, []);
        $moduleConfig = require $path;

        config([$key => array_replace_recursive($existing, $moduleConfig)]);
    }
}
