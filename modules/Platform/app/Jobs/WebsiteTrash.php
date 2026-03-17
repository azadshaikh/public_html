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
use Modules\Platform\Console\HestiaChangeWebTemplateCommand;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteService;

class WebsiteTrash implements ShouldQueue
{
    use ActivityTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        /**
         * The website ID to process.
         * We store the ID instead of the model because the website is soft-deleted
         * and SerializesModels won't find it without withTrashed().
         */
        public int $websiteId
    ) {}

    /**
     * Execute the job.
     *
     * Changes the website's nginx template to show a trashed page.
     */
    public function handle(): void
    {
        $this->queueMonitorLabel('Website #'.$this->websiteId);
        // Fetch the website with trashed to handle soft-deleted records
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteTrash job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteTrash job started', [
                'website_id' => $website->id,
                'status' => $website->status,
            ]);

            // Get the trashed template
            $template = HestiaChangeWebTemplateCommand::getTemplateForStatus('trashed');

            // Change the nginx template to show trashed page
            Artisan::call('platform:hestia:change-web-template', [
                'website_id' => $website->id,
                'template' => $template,
            ]);

            // Clear caches to ensure changes take effect immediately
            Artisan::call('platform:hestia:clear-cache', [
                'website_id' => $website->id,
            ]);

            // Stop queue workers - trashed websites shouldn't process jobs
            Artisan::call('platform:hestia:manage-queue-worker', [
                'website_id' => $website->id,
                'action' => 'stop',
            ]);

            // Suspend cron job - trashed websites shouldn't run scheduled tasks
            Artisan::call('platform:hestia:manage-cron', [
                'website_id' => $website->id,
                'action' => 'suspend',
            ]);

            // Remove CDN pull zone - trashed websites shouldn't serve through CDN
            $this->removeCdnPullZone($website);

            $this->logActivity($website, ActivityAction::UPDATE, 'Website trashed successfully on server.');

            // Sync website info to update queue worker and cron status in metadata
            resolve(WebsiteService::class)->syncWebsiteInfo($website);

            Log::info('WebsiteTrash job completed', [
                'website_id' => $website->id,
                'template' => $template,
            ]);
        } catch (Exception $exception) {
            Log::error('WebsiteTrash job failed', [
                'website_id' => $website->id,
                'error' => $exception->getMessage(),
            ]);

            $this->logActivity(
                $website,
                ActivityAction::UPDATE,
                $website->site_id.' website trash job failed: '.$exception->getMessage()
            );

            throw $exception;
        }
    }

    /**
     * Remove the CDN pull zone for the website if one exists.
     *
     * Non-fatal: failure is logged but doesn't block the trash operation.
     */
    private function removeCdnPullZone(Website $website): void
    {
        if ($website->skip_cdn || ! $website->pullzone_id) {
            return;
        }

        $cdnProvider = $website->cdnProvider ?? $website->dnsProvider;
        if (! $cdnProvider || $cdnProvider->vendor !== 'bunny') {
            return;
        }

        $pullzoneId = $website->pullzone_id;

        try {
            BunnyApi::deletePullZone($cdnProvider, $pullzoneId);

            // Clear CDN metadata so pullzone_id returns null (stale reference)
            $website->setMetadata('cdn', null);
            $website->save();

            Log::info('WebsiteTrash: CDN pull zone deleted', [
                'website_id' => $website->id,
                'pullzone_id' => $pullzoneId,
            ]);
        } catch (Exception $e) {
            Log::warning('WebsiteTrash: Failed to delete CDN pull zone', [
                'website_id' => $website->id,
                'pullzone_id' => $pullzoneId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
