<?php

namespace Modules\Platform\Jobs;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
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
use Modules\Platform\Notifications\WebsiteSuspended as NotificationWebsiteSuspended;
use Modules\Platform\Notifications\WebsiteSuspensionFailed as NotificationWebsiteSuspendFailed;
use Modules\Platform\Services\WebsiteService;
use Throwable;

class WebsiteSuspend implements ShouldQueue
{
    use ActivityTrait;
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
     * Uses custom nginx template instead of Hestia's native suspend functionality.
     * This allows for a custom suspended page while keeping the website files intact.
     */
    public function handle(): void
    {
        $this->queueMonitorLabel('Website #'.$this->websiteId);
        // Fetch with withTrashed() to handle soft-deleted websites
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteSuspend job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteSuspend job started', [
                'website_id' => $website->id,
                'status' => $website->status,
            ]);

            // Get the appropriate template for suspended status
            $template = HestiaChangeWebTemplateCommand::getTemplateForStatus('suspended');

            // Change the nginx template to show suspended page
            Artisan::call('platform:hestia:change-web-template', [
                'website_id' => $website->id,
                'template' => $template,
            ]);

            // Clear caches to ensure changes take effect immediately
            Artisan::call('platform:hestia:clear-cache', [
                'website_id' => $website->id,
            ]);

            // Stop queue workers - suspended websites shouldn't process jobs
            Artisan::call('platform:hestia:manage-queue-worker', [
                'website_id' => $website->id,
                'action' => 'stop',
            ]);

            // Suspend cron job - suspended websites shouldn't run scheduled tasks
            Artisan::call('platform:hestia:manage-cron', [
                'website_id' => $website->id,
                'action' => 'suspend',
            ]);

            // Notify user of success
            if ($website->updatedBy) {
                $website->updatedBy->notify(new NotificationWebsiteSuspended($website));
            }

            // Sync website info to update queue worker and cron status in metadata
            resolve(WebsiteService::class)->syncWebsiteInfo($website);

            Log::info('WebsiteSuspend job completed', [
                'website_id' => $website->id,
                'template' => $template,
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteSuspend job failed', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            if ($website->updatedBy) {
                $website->updatedBy->notify(new NotificationWebsiteSuspendFailed($website, $throwable->getMessage()));
            }

            $this->logActivity($website, ActivityAction::UPDATE, $website->site_id.' website suspend job failed: '.$throwable->getMessage());

            throw $throwable;
        }
    }
}
