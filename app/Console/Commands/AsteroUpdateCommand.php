<?php

namespace App\Console\Commands;

use App\Modules\ModuleManager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Command to update the Astero application to the latest version.
 *
 * This command handles the update process including database migrations,
 * seeder updates, module updates, and cache rebuilding with comprehensive
 * error handling and step-by-step progress tracking.
 *
 * Features:
 * - Step-by-step progress tracking
 * - Detailed logging for debugging
 * - Graceful error handling
 * - Module-aware updates
 */
class AsteroUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astero:update
                          {--type=main : Type of update (main or module)}
                          {--force : Force update without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the Astero application with latest release including migrations, seeders, and cache rebuild.';

    /**
     * Completed update steps for tracking
     */
    private array $completedSteps = [];

    /**
     * Update start time for performance tracking
     */
    private float $startTime;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->startTime = microtime(true);

        $force = $this->option('force');
        $type = $this->option('type');

        // Skip confirmation if forced or running non-interactively
        $skipConfirmation = $force || ! $this->input->isInteractive();

        if (! $skipConfirmation && ! $this->confirm('Are you sure you want to update? This action cannot be undone.')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            $this->displayHeader();

            if ($type === 'main') {
                return $this->executeMainUpdate();
            }

            $this->info('Module-specific update not yet implemented.');

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->handleUpdateFailure($exception);

            return self::FAILURE;
        }
    }

    /**
     * Execute the main application update process
     */
    private function executeMainUpdate(): int
    {
        $steps = [
            'clearConfigCache' => 'Clear configuration cache',
            'runMigrations' => 'Run database migrations',
            'runUpdateSeeders' => 'Run update seeders',
            'runModuleMigrations' => 'Run module migrations',
            'runModuleSeeders' => 'Run module update seeders',
            'ensureStorageLink' => 'Ensure storage link exists',
            'generateSitemap' => 'Generate sitemap',
            'rebuildCaches' => 'Rebuild application caches',
        ];

        $this->info('📋 Executing update steps...');

        foreach ($steps as $method => $description) {
            $this->executeStep($method, $description);
        }

        $this->displaySuccessMessage();

        return self::SUCCESS;
    }

    /**
     * Execute a single update step with error handling
     */
    private function executeStep(string $method, string $description): void
    {
        $this->line(sprintf('  ├─ %s...', $description));
        $stepStart = microtime(true);

        try {
            $this->{$method}();
            $this->completedSteps[] = $method;

            $duration = round(microtime(true) - $stepStart, 2);
            $this->line(sprintf('  │  ✅ %s completed in %ss', $description, $duration));
        } catch (Exception $exception) {
            $duration = round(microtime(true) - $stepStart, 2);
            $this->line(sprintf('  │  ❌ %s failed after %ss', $description, $duration));

            Log::error(sprintf("Update step '%s' failed", $method), [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'duration' => $duration,
            ]);

            throw new Exception(sprintf("Failed at step '%s': %s", $description, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * Clear configuration cache before update
     */
    private function clearConfigCache(): void
    {
        Artisan::call('config:clear');
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): void
    {
        $exitCode = Artisan::call('migrate', [
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $output = Artisan::output();
            throw new Exception('Migration execution failed. Output: '.$output);
        }
    }

    /**
     * Run update seeders for main application
     */
    private function runUpdateSeeders(): void
    {
        // Check if update:seed command exists
        try {
            $exitCode = Artisan::call('update:seed');
            if ($exitCode !== 0) {
                $this->warn('    ⚠️  Update seeder returned non-zero exit code');
            }
        } catch (Exception) {
            // update:seed command might not exist, which is okay
            $this->line('     ℹ️  No update:seed command found, skipping');
        }
    }

    /**
     * Run module migrations
     */
    private function runModuleMigrations(): void
    {
        $modules = resolve(ModuleManager::class)->enabled();

        foreach ($modules as $module) {
            $this->line('  │    - Migrating: '.$module->name);

            try {
                $migrationsPath = $module->basePath.'/database/migrations';

                if (is_dir($migrationsPath)) {
                    Artisan::call('migrate', [
                        '--path' => $migrationsPath,
                        '--realpath' => true,
                        '--force' => true,
                    ]);
                }
            } catch (Exception $e) {
                $this->warn(sprintf('    ⚠️  Module migration failed for %s: %s', $module->name, $e->getMessage()));
            }
        }
    }

    /**
     * Run module update seeders
     */
    private function runModuleSeeders(): void
    {
        $modules = resolve(ModuleManager::class)->enabled();

        foreach ($modules as $module) {
            $updateSeederClass = rtrim($module->namespace, '\\').'\\Database\\Seeders\\UpdateSeeder';

            if (class_exists($updateSeederClass)) {
                $this->line('  │    - Update seeding: '.$module->name);

                try {
                    Artisan::call('db:seed', [
                        '--class' => $updateSeederClass,
                        '--force' => true,
                    ]);
                } catch (Exception $e) {
                    $this->warn(sprintf('    ⚠️  Module update seeder failed for %s: %s', $module->name, $e->getMessage()));
                }
            }
        }
    }

    /**
     * Ensure storage link exists
     */
    private function ensureStorageLink(): void
    {
        $linkPath = public_path('storage');

        if (! is_link($linkPath) && ! is_dir($linkPath)) {
            Artisan::call('storage:link');
        } else {
            $this->line('     ℹ️  Storage link already exists');
        }
    }

    /**
     * Generate sitemap
     */
    private function generateSitemap(): void
    {
        try {
            Artisan::call('generate:sitemap');
        } catch (Exception) {
            // Sitemap generation is optional
            $this->line('     ℹ️  Sitemap generation skipped or not available');
        }
    }

    /**
     * Clear and rebuild application caches
     */
    private function rebuildCaches(): void
    {
        $this->line('  │  Clearing and rebuilding caches...');

        try {
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
            Cache::flush();
            $this->line('  │  ✅ Caches rebuilt successfully.');
        } catch (Exception $exception) {
            Log::warning('Failed to rebuild caches: '.$exception->getMessage());
            $this->warn('    ⚠️  Could not rebuild all application caches.');
        }
    }

    /**
     * Handle update failure with detailed logging
     */
    private function handleUpdateFailure(Throwable $e): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);

        Log::error('Astero update failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'duration' => $duration,
            'completed_steps' => $this->completedSteps,
        ]);

        $this->error('');
        $this->error('❌ Update failed!');
        $this->error(sprintf('⏱️  Failed after %s seconds', $duration));
        $this->error('📝 Error: '.$e->getMessage());
        $this->error('');
        $this->error('🔧 Troubleshooting:');
        $this->error('  • Check the logs for detailed error information');
        $this->error('  • Verify database connectivity');
        $this->error('  • Consider reverting to previous version if needed');
        $this->error('');
    }

    /**
     * Display update header with system information
     */
    private function displayHeader(): void
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                       ASTERO UPDATE                        ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');
        $this->info('🚀 Starting application update...');
        $this->info('📅 Started at: '.now()->format('Y-m-d H:i:s'));
        $this->info('🖥️  PHP Version: '.PHP_VERSION);
        $this->info('📦 Laravel Version: '.app()->version());
        $this->info('📦 Astero Version: '.app_version());
        $this->info('');
    }

    /**
     * Display success message with summary
     */
    private function displaySuccessMessage(): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);

        $this->info('');
        $this->info('🎉 Update completed successfully!');
        $this->info(sprintf('⏱️  Total time: %s seconds', $duration));
        $this->info('');
        $this->info('📋 Completed steps:');
        foreach ($this->completedSteps as $step) {
            $this->info('  ✅ '.$step);
        }

        $this->info('');
    }
}
