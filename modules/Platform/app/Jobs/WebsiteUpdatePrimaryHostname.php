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
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteProvisioningService;
use RuntimeException;
use Throwable;

class WebsiteUpdatePrimaryHostname implements ShouldQueue
{
    use ActivityTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $websiteId,
        public bool $useWww,
        public ?int $requestedByUserId = null,
    ) {}

    public function handle(WebsiteProvisioningService $websiteProvisioningService): void
    {
        $this->queueMonitorLabel('Website #'.$this->websiteId);

        /** @var Website|null $website */
        $website = Website::withTrashed()
            ->with(['domainRecord', 'providers', 'server'])
            ->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteUpdatePrimaryHostname job failed: Website not found', [
                'website_id' => $this->websiteId,
                'requested_by' => $this->requestedByUserId,
            ]);

            return;
        }

        $targetHostname = $this->useWww ? 'www' : 'apex';
        $website->setMetadata('primary_hostname_sync', [
            'status' => 'processing',
            'target' => $targetHostname,
            'requested_at' => now()->toIso8601String(),
            'requested_by' => $this->requestedByUserId,
            'message' => sprintf('Primary hostname reconciliation queued for %s.', $targetHostname),
        ]);
        $website->save();

        try {
            Log::info('WebsiteUpdatePrimaryHostname job started', [
                'website_id' => $website->id,
                'site_id' => $website->site_id,
                'target' => $targetHostname,
                'requested_by' => $this->requestedByUserId,
            ]);

            $result = $websiteProvisioningService->updatePrimaryHostname($website, $this->useWww);

            if (($result['status'] ?? 'error') === 'error') {
                $message = $result['message'] ?? 'Primary hostname reconciliation failed.';
                $website->setMetadata('primary_hostname_sync', [
                    'status' => 'failed',
                    'target' => $targetHostname,
                    'requested_at' => now()->toIso8601String(),
                    'requested_by' => $this->requestedByUserId,
                    'message' => $message,
                ]);
                $website->save();

                throw new RuntimeException($message);
            }

            $website->setMetadata('primary_hostname_sync', [
                'status' => $result['status'] ?? 'success',
                'target' => $targetHostname,
                'requested_at' => now()->toIso8601String(),
                'requested_by' => $this->requestedByUserId,
                'completed_at' => now()->toIso8601String(),
                'message' => $result['message'] ?? 'Primary hostname reconciled successfully.',
            ]);
            $website->save();

            Log::info('WebsiteUpdatePrimaryHostname job completed', [
                'website_id' => $website->id,
                'site_id' => $website->site_id,
                'target' => $targetHostname,
                'status' => $result['status'] ?? 'success',
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteUpdatePrimaryHostname job failed', [
                'website_id' => $website->id,
                'site_id' => $website->site_id,
                'target' => $targetHostname,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            $this->logActivity(
                $website,
                ActivityAction::UPDATE,
                $website->site_id.' primary hostname update failed: '.$throwable->getMessage()
            );

            throw $throwable;
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteUpdatePrimaryHostname failed() called but website not found', [
                'website_id' => $this->websiteId,
                'error' => $exception?->getMessage(),
            ]);

            return;
        }

        $targetHostname = $this->useWww ? 'www' : 'apex';

        $website->setMetadata('primary_hostname_sync', [
            'status' => 'failed',
            'target' => $targetHostname,
            'requested_by' => $this->requestedByUserId,
            'failed_at' => now()->toIso8601String(),
            'message' => $exception?->getMessage() ?? 'Primary hostname reconciliation failed.',
        ]);
        $website->save();

        Log::error('WebsiteUpdatePrimaryHostname failed for website #'.$website->id, [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            $website->site_id.' primary hostname update failed: '.($exception?->getMessage() ?? 'Unknown error')
        );
    }
}
