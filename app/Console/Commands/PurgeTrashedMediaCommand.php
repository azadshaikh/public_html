<?php

/**
 * Laravel Artisan Command: media:purge-trashed
 *
 * Permanently deletes soft-deleted media files from storage and the database after a configurable grace period.
 *
 * Usage:
 *   php artisan media:purge-trashed
 *   php artisan media:purge-trashed --no-delay
 *
 * Options:
 *   --no-delay   Run immediately without random startup delay (useful for manual testing)
 *
 * Scheduling:
 *   Add to Laravel scheduler for daily cleanup:
 *     Schedule::command('media:purge-trashed')->dailyAt('01:40')->runInBackground();
 *
 * Configuration:
 *   - MEDIA_TRASH_AUTO_DELETE_DAYS: Number of days before trashed files are deleted (-1 disables auto-deletion, 0 deletes on next run/within 24 hours)
 *   - setting('media_delete_trashed'): Must be enabled for deletion to run
 */

namespace App\Console\Commands;

use App\Models\CustomMedia;
use App\Services\MediaService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class PurgeTrashedMediaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:purge-trashed {--no-delay : Run the command without the random startup delay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently deletes soft-deleted media files from storage and the database after a configured grace period.';

    /**
     * Create a new command instance.
     */
    public function __construct(protected MediaService $mediaService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('no-delay')) {
            // To prevent a "thundering herd" scenario on multi-tenant servers where all instances
            // run this command at the same time, we introduce a random delay. This staggers
            // the execution across a 1-hour window.
            $delay = random_int(0, 3600);
            $this->info(sprintf('Waiting for %d seconds before starting to avoid server overload...', $delay));
            Sleep::sleep($delay);
        }

        $this->line('<fg=yellow;options=bold>Starting Trashed Media Deletion Process...</>');

        $delete_days = (int) config('media.trash_auto_delete_days', 30);

        if ($delete_days === -1) {
            $this->info('Trashed media auto-deletion is disabled (MEDIA_TRASH_AUTO_DELETE_DAYS = -1).');

            return 0;
        }

        // Use the service to get the count first
        $query = CustomMedia::onlyTrashed();
        if ($delete_days > 0) {
            $query = $query->where('deleted_at', '<=', Date::now()->subDays($delete_days));
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No trashed media found for deletion.');

            return 0;
        }

        $this->comment(sprintf('Found %d media files to be permanently deleted.', $count));

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s%');
        $progressBar->start();

        $deletedCount = 0;
        $failedCount = 0;

        $query->chunkById(100, function ($medias) use (&$deletedCount, &$failedCount, $progressBar): void {
            foreach ($medias as $media) {
                try {
                    $this->mediaService->permanentlyDeleteMedia($media);
                    $deletedCount++;
                } catch (Exception $e) {
                    Log::error(sprintf('Failed to delete media ID %s: ', $media->id).$e->getMessage());
                    $failedCount++;
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->line('<fg=green;options=bold>-----------------------------------</>');
        $this->line('<fg=green;options=bold>  Deletion Process Complete</>');
        $this->line('<fg=green;options=bold>-----------------------------------</>');
        $this->info(sprintf('Successfully deleted: %d files.', $deletedCount));

        if ($failedCount > 0) {
            $this->error(sprintf('Failed to delete: %d files. Please check logs for details.', $failedCount));
        }

        return 0;
    }
}
