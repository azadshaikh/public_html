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
use Illuminate\Support\Facades\Log;
use Modules\Platform\Console\HestiaChangeWebTemplateCommand;
use Modules\Platform\Jobs\Concerns\InteractsWithWebsiteLifecycleExecution;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Website;

class WebsiteTrash implements ShouldQueue
{
    use ActivityTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithWebsiteLifecycleExecution;
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
        $this->initializeLifecycleMonitor('Website #'.$this->websiteId);
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
            $this->callLifecycleArtisanStep('WebsiteTrash', 'Change web template', 'platform:hestia:change-web-template', [
                'website_id' => $website->id,
                'template' => $template,
            ]);

            // Clear caches to ensure changes take effect immediately
            $this->callLifecycleArtisanStep('WebsiteTrash', 'Clear website cache', 'platform:hestia:clear-cache', [
                'website_id' => $website->id,
            ]);

            // Stop queue workers - trashed websites shouldn't process jobs
            $this->callLifecycleArtisanStep('WebsiteTrash', 'Stop queue workers', 'platform:hestia:manage-queue-worker', [
                'website_id' => $website->id,
                'action' => 'stop',
            ]);

            // Suspend cron job - trashed websites shouldn't run scheduled tasks
            $this->callLifecycleArtisanStep('WebsiteTrash', 'Suspend cron job', 'platform:hestia:manage-cron', [
                'website_id' => $website->id,
                'action' => 'suspend',
            ]);

            // Remove CDN pull zone - trashed websites shouldn't serve through CDN
            $this->removeCdnPullZone($website);

            $this->logActivity($website, ActivityAction::UPDATE, 'Website trashed successfully on server.');

            // Update local runtime metadata without making another remote sync call.
            $this->updateRuntimeMetadataForTrash($website);

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

    private function updateRuntimeMetadataForTrash(Website $website): void
    {
        $website->setMetadata('queue_worker_status', 'stopped');
        $website->setMetadata('queue_worker_running_count', 0);
        $website->setMetadata(
            'queue_worker_total_count',
            max(0, (int) $website->getMetadata('queue_worker_total_count', 0))
        );
        $website->setMetadata('cron_status', 'suspended');
        $website->setMetadata('last_synced_at', now()->toIso8601String());
        $website->save();
    }
}
