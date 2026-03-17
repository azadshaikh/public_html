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
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Console\HestiaChangeWebTemplateCommand;
use Modules\Platform\Models\Website;
use Modules\Platform\Notifications\WebsiteUnexpirationFailed as NotificationWebsiteUnexpirationFailed;
use Modules\Platform\Notifications\WebsiteUnexpired as NotificationWebsiteUnexpired;
use Modules\Platform\Services\WebsiteService;
use Throwable;

class WebsiteUnExpired implements ShouldQueue
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
     * Restores an expired website by:
     * 1. Switching back to the active nginx template at server level
     * 2. Clearing caches
     * 3. Notifying the website's internal API
     * 4. Updating expiration dates if applicable
     */
    public function handle(): void
    {
        $this->queueMonitorLabel('Website #'.$this->websiteId);
        // Fetch with withTrashed() to handle soft-deleted websites
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteUnExpired job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteUnExpired job started', [
                'website_id' => $website->id,
                'status' => $website->status,
            ]);

            // First, restore the nginx template to active state at server level
            $template = HestiaChangeWebTemplateCommand::getTemplateForStatus('active');

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

            // Notify the website's internal API about unexpiration
            $this->notifyWebsiteApi($website);

            // Update website status and expiration dates
            $website->status = 'active';
            if (! empty($website->expired_at) && Date::parse($website->expired_at)->isPast()) {
                if (! in_array($website->type, ['trial', 'paid'])) {
                    $website->expired_at = null;
                } elseif ($website->type === 'trial') {
                    $website->expired_at = Date::now()->addDays(15);
                } else {
                    $cal_expired_at = Date::parse($website->created_at)->addDays(365);
                    $website->expired_at = $cal_expired_at;
                }
            }

            $website->save();

            // Notify user of success
            $website->refresh();
            $website->updatedBy?->notify(new NotificationWebsiteUnexpired($website));

            // Sync website info to update queue worker and cron status in metadata
            resolve(WebsiteService::class)->syncWebsiteInfo($website);

            Log::info('WebsiteUnExpired job completed', [
                'website_id' => $website->id,
                'template' => $template,
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteUnExpired job failed', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            $website->refresh();
            $website->updatedBy?->notify(new NotificationWebsiteUnexpirationFailed($website, $throwable->getMessage()));

            $this->logActivity($website, ActivityAction::RESTORE, $website->site_id.' website unexpire job failed: '.$throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * Notify the website's internal API about unexpiration.
     */
    private function notifyWebsiteApi(Website $website): void
    {
        $api_url = 'https://'.$website->domain.'/api/websites/expire';

        try {
            Http::withHeaders([
                'authorization' => $website->plain_secret_key, // Use decrypted token for client validation
                'Content-Type' => 'application/json',
            ])->withOptions(['verify' => false])->post($api_url, ['flag' => 0]);
        } catch (Throwable $throwable) {
            // Log but don't fail - the server-level template change is more important
            Log::warning('WebsiteUnExpired: Failed to notify website API', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
