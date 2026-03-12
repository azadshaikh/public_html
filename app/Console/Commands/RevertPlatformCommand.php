<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RevertPlatformCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:revert {--type=main} {--target-version} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revert the application to a previous version.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $version = $this->option('target-version');

        // If not forced, ask for confirmation
        if (! $force && ! $this->confirm('Are you sure you want to revert the application to a v'.$version.'? This action cannot be undone.')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $type = $this->option('type');

        if ($type === 'main') {
            $this->info('Main application revert process start...');
            $this->revertMainPlatform();
            $this->info('Main application revert process completed successfully.');
        } else {
            $this->info('Module application revert process start...');
        }

        return self::SUCCESS;
    }

    private function revertMainPlatform(): void
    {
        $this->info('Main application revert process start...');
        $this->warn('Version-based migration revert is no longer supported. Please use standard Laravel migration rollback commands.');
        $this->info('regenerating storage link to public folder');
        Artisan::call('storage:link');
        $this->info('Regenerating sitemaps..');
        Artisan::call('generate:sitemap');
        $this->info('Application revert process completed successfully.');
    }
}
