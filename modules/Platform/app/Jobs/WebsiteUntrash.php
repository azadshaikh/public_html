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
use Modules\Platform\Models\Website;

class WebsiteUntrash implements ShouldQueue
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
         * We store the ID instead of the model to safely handle the timing
         * where the website might still be soft-deleted when the job runs.
         */
        public int $websiteId
    ) {}

    /**
     * Execute the job.
     *
     * Restores the website from trash by changing the nginx template back to active.
     */
    public function handle(): void
    {
        $this->initializeLifecycleMonitor('Website #'.$this->websiteId);
        // Fetch the website with trashed to handle timing issues
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteUntrash job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteUntrash job started', [
                'website_id' => $website->id,
                'status' => $website->status,
            ]);

            // Get the active template
            $template = HestiaChangeWebTemplateCommand::getTemplateForStatus('active');

            // Change the nginx template back to active
            $this->callLifecycleArtisanStep('WebsiteUntrash', 'Change web template', 'platform:hestia:change-web-template', [
                'website_id' => $website->id,
                'template' => $template,
            ]);

            // Clear caches to ensure changes take effect immediately
            $this->callLifecycleArtisanStep('WebsiteUntrash', 'Clear website cache', 'platform:hestia:clear-cache', [
                'website_id' => $website->id,
            ]);

            // Start queue workers - website is now active again
            $this->callLifecycleArtisanStep('WebsiteUntrash', 'Start queue workers', 'platform:hestia:manage-queue-worker', [
                'website_id' => $website->id,
                'action' => 'start',
            ]);

            // Unsuspend cron job - website is now active again
            $this->callLifecycleArtisanStep('WebsiteUntrash', 'Unsuspend cron job', 'platform:hestia:manage-cron', [
                'website_id' => $website->id,
                'action' => 'unsuspend',
            ]);

            // Recreate CDN pull zone if website uses CDN
            $this->recreateCdnPullZone($website);

            $this->logActivity($website, ActivityAction::UPDATE, 'Website restored from trash successfully on server.');

            $this->updateRuntimeMetadataForRestore($website);

            Log::info('WebsiteUntrash job completed', [
                'website_id' => $website->id,
                'template' => $template,
            ]);
        } catch (Exception $exception) {
            Log::error('WebsiteUntrash job failed', [
                'website_id' => $website->id,
                'error' => $exception->getMessage(),
            ]);

            $this->logActivity(
                $website,
                ActivityAction::UPDATE,
                $website->site_id.' website untrash job failed: '.$exception->getMessage()
            );

            throw $exception;
        }
    }

    /**
     * Recreate the CDN pull zone for the website if it was using CDN before trashing.
     *
     * Runs setup-cdn (creates pull zone + adds hostnames) then configure-cdn-ssl
     * (uploads SSL cert + enables ForceSSL). DNS records don't need updating since
     * the pull zone name (from uid) stays the same.
     *
     * Non-fatal: failure is logged but doesn't block the restore operation.
     */
    private function recreateCdnPullZone(Website $website): void
    {
        if ($website->skip_cdn) {
            return;
        }

        $cdnProvider = $website->cdnProvider ?? $website->dnsProvider;
        if (! $cdnProvider || $cdnProvider->vendor !== 'bunny') {
            return;
        }

        try {
            $this->throwIfLifecycleCancellationRequested('WebsiteUntrash', 'Recreate CDN pull zone');

            // Step 1: Recreate pull zone (same name from uid, adds hostnames)
            $this->callLifecycleArtisanStep('WebsiteUntrash', 'Recreate CDN pull zone', 'platform:bunny:setup-cdn', [
                'website_id' => $website->id,
            ]);

            // Refresh model to pick up new CDN metadata set by setup-cdn
            $website->refresh();

            // Step 2: Upload SSL certificate to the new pull zone
            if ($website->pullzone_id) {
                $this->callLifecycleArtisanStep('WebsiteUntrash', 'Configure CDN SSL', 'platform:bunny:configure-cdn-ssl', [
                    'website_id' => $website->id,
                ]);
            }

            Log::info('WebsiteUntrash: CDN pull zone recreated', [
                'website_id' => $website->id,
                'pullzone_id' => $website->pullzone_id,
            ]);
        } catch (Exception $e) {
            Log::warning('WebsiteUntrash: Failed to recreate CDN pull zone', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateRuntimeMetadataForRestore(Website $website): void
    {
        $expectedWorkerCount = max(1, (int) $website->getMetadata('queue_worker_total_count', 1));

        $website->setMetadata('queue_worker_status', 'running');
        $website->setMetadata('queue_worker_running_count', $expectedWorkerCount);
        $website->setMetadata('queue_worker_total_count', $expectedWorkerCount);
        $website->setMetadata('cron_status', 'active');
        $website->setMetadata('last_synced_at', now()->toIso8601String());
        $website->save();
    }
}
