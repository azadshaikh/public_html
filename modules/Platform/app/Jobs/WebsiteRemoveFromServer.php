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
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Jobs\Concerns\InteractsWithWebsiteLifecycleExecution;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerService;
use RuntimeException;
use Throwable;

/**
 * Job to remove a website from the Hestia server while keeping the database record.
 *
 * This job:
 * 1. Removes queue worker configuration
 * 2. Deletes the Hestia user (files, database, etc.)
 * 3. Sets the website status to 'deleted'
 * 4. Does NOT delete the database record
 */
class WebsiteRemoveFromServer implements ShouldQueue
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
         */
        public int $websiteId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->initializeLifecycleMonitor('Website #'.$this->websiteId);
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::warning('WebsiteRemoveFromServer job: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        if (! $website->trashed()) {
            Log::warning('WebsiteRemoveFromServer job: Website is not trashed', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        if ($website->status === WebsiteStatus::Deleted) {
            Log::info('WebsiteRemoveFromServer job: Website already removed from server', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteRemoveFromServer job started', [
                'website_id' => $website->id,
                'site_id' => $website->site_id,
            ]);

            $this->throwIfLifecycleCancellationRequested('WebsiteRemoveFromServer', 'Delete website from server');

            // Remove queue worker configuration
            $this->removeQueueWorkerConfiguration($website);

            // Delete from Hestia server (user, files, database)
            $deleteExit = Artisan::call('platform:hestia:delete-website', ['website_id' => $website->id]);
            $deleteOutput = trim(Artisan::output());
            throw_if($deleteExit !== 0, RuntimeException::class, $deleteOutput !== '' ? $deleteOutput : 'Hestia delete command failed');

            // Update website status to 'deleted'
            $website->status = WebsiteStatus::Deleted;
            $website->save();

            // Sync server to update domain count
            $server = $website->server;
            if ($server) {
                try {
                    $serverService = resolve(ServerService::class);
                    $serverService->syncServerInfo($server);
                } catch (Throwable $e) {
                    Log::warning('Server sync failed after website removal', [
                        'server_id' => $server->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logActivity(
                $website,
                ActivityAction::UPDATE,
                'Website removed from server. Status set to deleted.'
            );

            Log::info('WebsiteRemoveFromServer job completed', [
                'website_id' => $website->id,
                'site_id' => $website->site_id,
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteRemoveFromServer job failed', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            $this->logActivity(
                $website,
                ActivityAction::UPDATE,
                'Website server removal failed: '.$throwable->getMessage()
            );

            throw $throwable;
        }
    }

    private function removeQueueWorkerConfiguration(Website $website): void
    {
        $server = $website->server;
        if (! $server) {
            Log::warning('WebsiteRemoveFromServer: Skipping Supervisor cleanup because website server is missing', [
                'website_id' => $website->id,
            ]);

            return;
        }

        $response = HestiaClient::execute(
            'a-manage-queue-worker',
            $server,
            [
                'arg1' => $website->website_username,
                'arg2' => $website->domain,
                'arg3' => 'remove',
            ]
        );

        if (! ($response['success'] ?? false)) {
            $code = (int) ($response['code'] ?? 1);
            $message = $response['message'] ?? 'Failed to remove Supervisor queue worker configuration';
            // Hestia code 3: object doesn't exist. Treat as idempotent success.
            throw_if($code !== 3, RuntimeException::class, $message);
        }
    }
}
