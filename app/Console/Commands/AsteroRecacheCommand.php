<?php

namespace App\Console\Commands;

use App\Modules\ModuleManager;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Request;
use RuntimeException;
use Throwable;

/**
 * Command to clear all caches and optionally rebuild them.
 *
 * This command is useful after environment changes, deployments,
 * or when you need to ensure all cached data is fresh.
 *
 * Usage:
 * - php artisan astero:recache          (clear and rebuild all caches)
 * - php artisan astero:recache --clear  (only clear caches, don't rebuild)
 */
class AsteroRecacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astero:recache
                            {--clear : Only clear caches without rebuilding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all application caches and optionally rebuild them. Useful after env changes or deployments.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Astero Cache Management');
        $this->newLine();

        // Guard: never rebuild caches when running inside a test process.
        // PHPUnit/Pest set APP_ENV=testing which would poison the config cache.
        if ($this->isRunningInTestProcess()) {
            $this->components->warn('Test environment detected — skipping cache rebuild to prevent poisoning the config cache.');

            return Command::SUCCESS;
        }

        $clearOnly = $this->option('clear');

        // Step 1: Clear all caches
        $this->clearAllCaches();

        // Step 2: Rebuild caches (unless --clear flag is set)
        if (! $clearOnly) {
            // Clear stale env vars so subprocesses read .env fresh from disk
            $this->clearStaleEnvVars();

            $this->newLine();
            $this->rebuildCaches();

            // Step 2b: Validate the rebuilt config cache isn't poisoned
            $this->validateConfigCache();
        }

        // Step 3: Restart queue workers
        $this->newLine();
        $this->restartQueueWorkers();

        $this->newLine();
        $this->components->info($clearOnly
            ? 'All caches cleared successfully!'
            : 'All caches cleared and rebuilt successfully!');
        $this->newLine();

        return Command::SUCCESS;
    }

    /**
     * Clear all application caches.
     */
    private function clearAllCaches(): void
    {
        // Reset OPcache first to ensure fresh PHP code/config reading
        $this->runCacheTask('Resetting OPcache', function (): void {
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
        });

        $this->runCacheTask('Clearing optimized caches', function (): void {
            Artisan::call('optimize:clear');
        });

        $this->runCacheTask('Clearing event cache', function (): void {
            Artisan::call('event:clear');
        });

        // Clear navigation cache
        $this->runCacheTask('Clearing navigation cache', function (): void {
            Artisan::call('navigation:clear-cache', ['--all' => true]);
        });

        // Clear menu cache (frontend menus) — only available when CMS module is enabled
        if (module_enabled('CMS')) {
            $this->runCacheTask('Clearing menu cache', function (): void {
                Artisan::call('menu:clear-cache');
            });
        }

        $this->runCacheTask('Clearing module services cache', function (): void {
            $this->clearModulesCacheFile();
        });
    }

    /**
     * Rebuild all application caches.
     *
     * Cache-building commands run as subprocesses with explicit .env values
     * passed via environment. This ensures fresh values from disk are used,
     * not stale values inherited from the parent process.
     */
    private function rebuildCaches(): void
    {
        $this->runCacheTask('Refreshing module manifest', function (): void {
            resolve(ModuleManager::class)->enabled();
        });

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $freshEnv = $this->loadFreshEnvFromFile();

        $this->runCacheTask('Optimizing caches', function () use ($php, $artisan, $freshEnv): void {
            $result = Process::path(base_path())->env($freshEnv)->run([$php, $artisan, 'optimize', '--except=routes', '--no-interaction']);
            if (! $result->successful()) {
                throw new RuntimeException('Optimize command failed: '.trim($result->errorOutput() ?: $result->output()));
            }
        });

        $this->runCacheTask('Caching routes', function () use ($php, $artisan, $freshEnv): void {
            $result = Process::path(base_path())->env($freshEnv)->run([$php, $artisan, 'route:cache', '--no-interaction']);
            if (! $result->successful()) {
                $this->clearRoutesCacheFile();
                Log::warning('Route cache failed; cleared cached routes file to avoid stale routes.');
                throw new RuntimeException('Route cache failed: '.trim($result->errorOutput() ?: $result->output()));
            }
        });

        $this->runCacheTask('Caching events', function () use ($php, $artisan, $freshEnv): void {
            $result = Process::path(base_path())->env($freshEnv)->run([$php, $artisan, 'event:cache', '--no-interaction']);
            if (! $result->successful()) {
                throw new RuntimeException('Event cache failed: '.trim($result->errorOutput() ?: $result->output()));
            }
        });
    }

    /**
     * Restart queue workers so they pick up fresh code and config.
     */
    private function restartQueueWorkers(): void
    {
        $this->runCacheTask('Restarting queue workers', function (): void {
            Artisan::call('queue:restart');
        });
    }

    private function runCacheTask(string $label, callable $callback): void
    {
        $this->components->task($label, function () use ($callback, $label): bool {
            try {
                $callback();
            } catch (Throwable $throwable) {
                $this->components->warn($label.' failed: '.$throwable->getMessage());

                return false;
            }

            return true;
        });
    }

    private function clearRoutesCacheFile(): void
    {
        try {
            $path = app()->getCachedRoutesPath();
            if (File::exists($path)) {
                File::delete($path);
            }
        } catch (Throwable $throwable) {
            Log::warning('Failed to clear cached routes file', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function clearModulesCacheFile(): void
    {
        try {
            $servicesPath = app()->getCachedServicesPath();
            $modulesPath = str_replace('services.php', 'modules.php', $servicesPath);
            if (File::exists($modulesPath)) {
                File::delete($modulesPath);
            }
        } catch (Throwable $throwable) {
            Log::warning('Failed to clear cached module services file', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Detect whether this process was spawned by PHPUnit / Pest.
     *
     * Checks both the in-memory config and the raw process environment
     * so it catches inherited-env cases too.
     */
    private function isRunningInTestProcess(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        // Catch inherited env vars from a parent PHPUnit process
        $envVal = Request::server('APP_ENV') ?? Env::get('APP_ENV', getenv('APP_ENV'));

        return $envVal === 'testing';
    }

    /**
     * Clear .env keys from the current process environment.
     *
     * This ensures subprocesses spawned for cache rebuilding do NOT inherit
     * stale values via putenv(). Each subprocess starts a fresh PHP interpreter
     * that reads .env from disk through Dotenv's proper parser.
     */
    private function clearStaleEnvVars(): void
    {
        try {
            $envPath = base_path('.env');
            if (! File::exists($envPath)) {
                return;
            }

            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, '#')) {
                    continue;
                }

                if (! str_contains($line, '=')) {
                    continue;
                }

                [$key] = explode('=', $line, 2);
                $key = trim($key);

                // Unset from all env sources so child processes don't inherit stale values
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            }
        } catch (Throwable $throwable) {
            Log::warning('Failed to clear stale env vars', ['error' => $throwable->getMessage()]);
        }
    }

    /**
     * Load fresh .env values from disk for passing to subprocesses.
     *
     * Returns an array of KEY => value pairs read directly from .env file.
     * These are passed explicitly to Process::env() to override any inherited
     * environment variables from the parent process.
     *
     * @return array<string, string>
     */
    private function loadFreshEnvFromFile(): array
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return [];
        }

        $env = [];
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2) + [1 => ''];
            $key = trim($key);
            $value = trim($value);

            // Remove surrounding quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            $env[$key] = $value;
        }

        return $env;
    }

    /**
     * Validate the rebuilt config cache doesn't contain test-environment values.
     *
     * If poisoned, the cache file is deleted so the app falls back to
     * reading .env directly until the next successful cache rebuild.
     */
    private function validateConfigCache(): void
    {
        try {
            $cachePath = app()->getCachedConfigPath();
            if (! File::exists($cachePath)) {
                return;
            }

            $config = require $cachePath;
            $appEnv = $config['app']['env'] ?? null;
            $sessionDriver = $config['session']['driver'] ?? null;

            if ($appEnv === 'testing' || $sessionDriver === 'array') {
                File::delete($cachePath);
                $this->components->warn(
                    sprintf('Deleted poisoned config cache (APP_ENV=%s, SESSION_DRIVER=%s). ', $appEnv, $sessionDriver)
                    .'The app will read .env directly until caches are rebuilt.'
                );

                Log::warning('Deleted poisoned config cache', [
                    'app_env' => $appEnv,
                    'session_driver' => $sessionDriver,
                ]);
            }
        } catch (Throwable $throwable) {
            Log::warning('Config cache validation failed', ['error' => $throwable->getMessage()]);
        }
    }
}
