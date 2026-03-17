<?php

namespace Modules\Platform\Jobs;

use App\Traits\IsMonitored;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Console\HestiaChangeWebTemplateCommand;
use Modules\Platform\Models\Website;
use Modules\Platform\Notifications\WebsiteExpirationFailed as NotificationWebsiteExpirationFailed;
use Modules\Platform\Notifications\WebsiteExpired as NotificationWebsiteExpired;
use Modules\Platform\Services\WebsiteService;
use Throwable;

class WebsiteExpired implements ShouldQueue
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
     * Handles website expiration by:
     * 1. Notifying the website's API (internal flag)
     * 2. Switching to expired nginx template at server level
     * 3. Clearing caches
     */
    public function handle(): void
    {
        $this->queueMonitorLabel('Website #'.$this->websiteId);
        // Fetch with withTrashed() to handle soft-deleted websites
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteExpired job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteExpired job started', [
                'website_id' => $website->id,
                'status' => $website->status,
            ]);

            // First, notify the website's internal API about expiration
            $this->notifyWebsiteApi($website);

            // Then, change the nginx template to show expired page at server level
            $template = HestiaChangeWebTemplateCommand::getTemplateForStatus('expired');

            Artisan::call('platform:hestia:change-web-template', [
                'website_id' => $website->id,
                'template' => $template,
            ]);

            // Clear caches to ensure changes take effect immediately
            Artisan::call('platform:hestia:clear-cache', [
                'website_id' => $website->id,
            ]);

            // Stop queue workers - expired websites shouldn't process jobs
            Artisan::call('platform:hestia:manage-queue-worker', [
                'website_id' => $website->id,
                'action' => 'stop',
            ]);

            // Suspend cron job - expired websites shouldn't run scheduled tasks
            Artisan::call('platform:hestia:manage-cron', [
                'website_id' => $website->id,
                'action' => 'suspend',
            ]);

            // Update website status
            $website->status = 'expired';
            $website->expired_on = now();
            $website->save();

            // Notify user of success
            $website->refresh();
            $website->updatedBy?->notify(new NotificationWebsiteExpired($website));

            // Sync website info to update queue worker and cron status in metadata
            resolve(WebsiteService::class)->syncWebsiteInfo($website);

            Log::info('WebsiteExpired job completed', [
                'website_id' => $website->id,
                'template' => $template,
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteExpired job failed', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            $website->refresh();
            $website->updatedBy?->notify(new NotificationWebsiteExpirationFailed($website, $throwable->getMessage()));

            activity('Platform')->performedOn($website)->event('expired')->log($website->site_id.' website expiration job failed: '.$throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * Notify the website's internal API about expiration.
     */
    private function notifyWebsiteApi(Website $website): void
    {
        $api_url = 'https://'.$website->domain.'/api/websites/expire';

        try {
            Http::withHeaders([
                'authorization' => $website->plain_secret_key, // Use decrypted token for client validation
                'Content-Type' => 'application/json',
            ])->withOptions(['verify' => false])->post($api_url, ['flag' => 1]);
        } catch (Throwable $throwable) {
            // Log but don't fail - the server-level template change is more important
            Log::warning('WebsiteExpired: Failed to notify website API', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
