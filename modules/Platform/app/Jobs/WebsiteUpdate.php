<?php

namespace Modules\Platform\Jobs;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use App\Traits\IsMonitored;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Website;
use Modules\Platform\Notifications\WebsiteUpdated as NotificationWebsiteUpdated;
use Modules\Platform\Notifications\WebsiteUpdateFailed as NotificationWebsiteUpdateFailed;
use Throwable;

/**
 * Handles the end-to-end update of a website to the latest version.
 *
 * This job is dispatched when a user requests to update a website. It offloads the
 * long-running update task to a background queue by calling the master
 * 'platform:update-website' Artisan command.
 *
 * This keeps the UI responsive and leverages Laravel's queue for reliability.
 */
class WebsiteUpdate implements ShouldQueue
{
    use ActivityTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * The website ID to be updated.
     */
    public int $websiteId;

    /**
     * Create a new job instance.
     */
    public function __construct(Website $website)
    {
        $this->websiteId = $website->id;
    }

    /**
     * Execute the job.
     *
     * This method now acts as a simple wrapper, calling the master update command
     * to handle the orchestration. This adheres to the DRY principle, ensuring the
     * update logic is defined in only one place.
     *
     * @throws Exception if the underlying Artisan command fails.
     */
    public function handle(): void
    {
        $this->queueMonitorLabel('Website #'.$this->websiteId);
        // Fetch the website - use withTrashed for consistency
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteUpdate job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteUpdate job started', [
                'website_id' => $website->id,
                'site_id' => $website->site_id,
                'current_version' => $website->astero_version,
            ]);

            // Call the single, master orchestration command.
            // The command itself contains all the logic for the sequence of steps.
            Artisan::call('platform:update-website', ['website_id' => $website->id]);

            // If the command completes without throwing an exception, it's a success.
            $this->onSuccess($website);

            Log::info('WebsiteUpdate job completed', [
                'website_id' => $website->id,
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteUpdate job failed', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            // Record a user-friendly activity log entry.
            $this->logActivity($website, ActivityAction::UPDATE, $website->site_id.' website update failed: '.$throwable->getMessage());

            // Notify the user who initiated the action about the failure.
            if ($website->updatedBy) {
                $website->updatedBy->notify(
                    new NotificationWebsiteUpdateFailed($website)
                );
            }

            // Re-throw the exception. Laravel's queue worker will catch it and
            // automatically call the failed() method.
            throw $throwable;
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is automatically invoked by the queue worker when an exception is thrown
     * from the `handle()` method. It logs the failure and notifies the user.
     *
     * @param  Throwable  $exception  The exception that caused the job to fail.
     */
    public function failed(?Throwable $exception = null): void
    {
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteUpdate failed() called but website not found', [
                'website_id' => $this->websiteId,
                'error' => $exception?->getMessage(),
            ]);

            return;
        }

        // Log the detailed error for debugging purposes.
        Log::error('WebsiteUpdate failed for website #'.$website->id, [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }

    /**
     * Handles the successful completion of the job.
     */
    private function onSuccess(Website $website): void
    {
        if ($website->updatedBy) {
            $website->updatedBy->notify(new NotificationWebsiteUpdated($website));
        }

        $this->logActivity($website, ActivityAction::UPDATE, $website->site_id.' website update completed successfully.');
    }
}
