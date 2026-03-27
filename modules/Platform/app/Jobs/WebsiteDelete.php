<?php

namespace Modules\Platform\Jobs;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Traits\ActivityTrait;
use App\Traits\IsMonitored;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Events\WebsiteDeletedEvent as EventsWebsiteDeleted;
use Modules\Platform\Jobs\Concerns\InteractsWithWebsiteLifecycleExecution;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;
use Modules\Platform\Notifications\WebsiteDeleted as NotificationWebsiteDeleted;
use Modules\Platform\Notifications\WebsiteDeletionFailed as NotificationWebsiteDeleteFailed;
use Modules\Platform\Services\ServerService;
use RuntimeException;
use Throwable;

class WebsiteDelete implements ShouldQueue
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
         * We store the ID instead of the model because the website is soft-deleted.
         */
        public int $websiteId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->initializeLifecycleMonitor('Website #'.$this->websiteId);
        // Fetch with withTrashed() since website is already soft-deleted when being permanently deleted
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteDelete job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            Log::info('WebsiteDelete job started', [
                'website_id' => $website->id,
                'site_id' => $website->site_id,
            ]);

            $this->throwIfLifecycleCancellationRequested('WebsiteDelete', 'Delete website from server');

            // Only cleanup Hestia if status is NOT 'deleted' (server data already removed)
            if ($website->status !== WebsiteStatus::Deleted) {
                $this->removeQueueWorkerConfiguration($website);
                $this->deleteFromHestiaServer($website);
            } else {
                Log::info('WebsiteDelete: Skipping Hestia cleanup (status is deleted)', [
                    'website_id' => $website->id,
                ]);
            }

            // Store values before deletion
            $server = $website->server;
            $updatedByUserId = $website->updated_by;
            $siteId = $website->site_id;
            $preservedDomainId = $website->domain_id;
            $preservedDomainName = $website->domainRecord?->name;

            DB::transaction(function () use ($website): void {
                $website->secrets()->forceDelete();
                $website->notes()->forceDelete();
                $website->forceDelete();
            });

            if ($preservedDomainId && $preservedDomainName) {
                Log::info('WebsiteDelete: preserved root domain and SSL assets for future reuse', [
                    'website_id' => $this->websiteId,
                    'domain_id' => $preservedDomainId,
                    'domain_name' => $preservedDomainName,
                ]);
            }

            // Sync server to update domain count after website deletion
            if ($server) {
                try {
                    $serverService = resolve(ServerService::class);
                    $syncResult = $serverService->syncServerInfo($server);

                    if ($syncResult['success'] ?? false) {
                        Log::info('Server synced after website deletion', [
                            'server_id' => $server->id,
                            'website_id' => $this->websiteId,
                        ]);
                    } else {
                        Log::warning('Server sync completed with warnings after website deletion', [
                            'server_id' => $server->id,
                            'website_id' => $this->websiteId,
                            'message' => $syncResult['message'] ?? 'Unknown',
                        ]);
                    }
                } catch (Exception $e) {
                    // Log error but don't fail the job - server sync is not critical
                    Log::error('Failed to sync server after website deletion', [
                        'server_id' => $server->id,
                        'website_id' => $this->websiteId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Notify user of success
            $updatedBy = $updatedByUserId ? User::query()->find($updatedByUserId) : null;
            if ($updatedBy) {
                // Create a temporary website object for notification (since it's been deleted)
                $tempWebsite = new Website;
                $tempWebsite->id = $this->websiteId;
                $tempWebsite->site_id = $siteId;
                $updatedBy->notify(new NotificationWebsiteDeleted($tempWebsite));
            }

            if ($server) {
                event(new EventsWebsiteDeleted($server));
            }

            Log::info('WebsiteDelete job completed', [
                'website_id' => $this->websiteId,
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteDelete job failed', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            $this->logActivity($website, ActivityAction::DELETE, $website->site_id.' website delete job failed: '.$throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception = null): void
    {
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteDelete job failed in failed() method: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        Log::error('WebsiteDelete job failed', [
            'website_id' => $website->id,
            'error' => $exception?->getMessage(),
        ]);

        $updatedBy = $website->updated_by ? User::query()->find($website->updated_by) : null;
        if ($updatedBy) {
            $updatedBy->notify(new NotificationWebsiteDeleteFailed($website, $exception?->getMessage() ?? 'Unknown error'));
        }

        $this->logActivity(
            $website,
            ActivityAction::DELETE,
            $website->site_id.' website delete job failed: '.($exception?->getMessage() ?? 'Unknown error')
        );
    }

    private function removeQueueWorkerConfiguration(Website $website): void
    {
        $server = $website->server;
        if (! $server) {
            Log::warning('WebsiteDelete: Skipping Supervisor cleanup because website server is missing', [
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

    private function deleteFromHestiaServer(Website $website): void
    {
        $exitCode = Artisan::call('platform:hestia:delete-website', ['website_id' => $website->id]);

        if ($exitCode !== 0) {
            $output = trim(Artisan::output());

            throw new RuntimeException(
                $output !== '' ? $output : 'Failed to delete website user from Hestia'
            );
        }
    }
}
