<?php

namespace Modules\Platform\Jobs;

use App\Traits\IsMonitored;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Console\HestiaChangeWebTemplateCommand;
use Modules\Platform\Models\Website;
use Modules\Platform\Notifications\WebsiteActivated as NotificationWebsiteActivated;
use Modules\Platform\Notifications\WebsiteActivationFailed as NotificationWebsiteActivationFailed;
use Modules\Platform\Services\WebsiteService;
use Throwable;

class WebsiteUnsuspend implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * The website ID to process.
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
     * Restores the website by switching back to the active nginx template.
     * This reverses the template-based suspension/expiration without using Hestia's native unsuspend.
     */
    public function handle(): void
    {
        $this->queueMonitorLabel('Website #'.$this->websiteId);
        // Fetch with withTrashed() to handle soft-deleted websites
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteUnsuspend job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteUnsuspend job started', [
                'website_id' => $website->id,
                'status' => $website->status,
            ]);

            // Get the appropriate template for active status
            $template = HestiaChangeWebTemplateCommand::getTemplateForStatus('active');

            // Change the nginx template back to normal
            Artisan::call('platform:hestia:change-web-template', [
                'website_id' => $website->id,
                'template' => $template,
            ]);

            // Clear caches to ensure changes take effect immediately
            Artisan::call('platform:hestia:clear-cache', [
                'website_id' => $website->id,
            ]);

            // Start queue workers - website is now active again
            Artisan::call('platform:hestia:manage-queue-worker', [
                'website_id' => $website->id,
                'action' => 'start',
            ]);

            // Unsuspend cron job - website is now active again
            Artisan::call('platform:hestia:manage-cron', [
                'website_id' => $website->id,
                'action' => 'unsuspend',
            ]);

            // Notify user of success
            $website->refresh();
            $website->updatedBy?->notify(new NotificationWebsiteActivated($website));

            // Sync website info to update queue worker and cron status in metadata
            resolve(WebsiteService::class)->syncWebsiteInfo($website);

            Log::info('WebsiteUnsuspend job completed', [
                'website_id' => $website->id,
                'template' => $template,
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteUnsuspend job failed', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            $website->refresh();
            $website->updatedBy?->notify(new NotificationWebsiteActivationFailed($website, $throwable->getMessage()));

            activity('Platform')->performedOn($website)->event('Unsuspend')->log($website->site_id.' website unsuspend job failed: '.$throwable->getMessage());

            throw $throwable;
        }
    }
}
