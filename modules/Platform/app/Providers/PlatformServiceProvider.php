<?php

declare(strict_types=1);

namespace Modules\Platform\Providers;

use App\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class PlatformServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'platform';
    }

    public function register(): void
    {
        parent::register();

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        $this->registerAllConfigFiles();
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerCommands();
        $this->registerCommandSchedules();
    }

    protected function registerCommands(): void
    {
        $consolePath = $this->modulePath('app/Console');

        if (! is_dir($consolePath)) {
            return;
        }

        $finder = new Finder;
        $finder->files()->name('*.php')->in($consolePath);

        $classes = [];

        foreach ($finder as $file) {
            $class = '\\Modules\\Platform\\Console\\'.$file->getBasename('.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isAbstract()) {
                $classes[] = $class;
            }
        }

        if ($classes !== []) {
            $this->commands($classes);
        }
    }

    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('platform:hestia:mark-website-expired')->dailyAt('23:30')->runInBackground();
            $schedule->command('platform:hestia:delete-expired-websites')->dailyAt('23:55')->runInBackground();
            $schedule->command('platform:dns:poll-pending')->everyMinute()->runInBackground();
            $schedule->command('platform:ssl:renew-expiring')->dailyAt('02:00')->runInBackground();
        });
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

        /** @var array<mixed> $existing */
        $existing = config($key, []);
        /** @var array<mixed> $moduleConfig */
        $moduleConfig = require $path;

        config([$key => array_replace_recursive($existing, $moduleConfig)]);
    }
}
