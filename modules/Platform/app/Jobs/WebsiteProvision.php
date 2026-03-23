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
use Illuminate\Support\Sleep;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Website;
use Modules\Platform\Notifications\WebsiteCreated as WebsiteCreatedNotification;
use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\WebsiteService;
use Throwable;

/**
 * Handles the end-to-end provisioning of a new website on a Hestia control panel.
 *
 * This job is dispatched after a new website record is created. It offloads the
 * long-running provisioning task to a background queue by calling the master
 * 'platform:provision-website' Artisan command.
 *
 * This keeps the UI responsive and leverages Laravel's queue for reliability.
 */
class WebsiteProvision implements ShouldQueue
{
    use ActivityTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    private const int WEBSITE_SYNC_MAX_ATTEMPTS = 4;

    private const int WEBSITE_SYNC_RETRY_DELAY_SECONDS = 5;

    /**
     * The website ID to be provisioned.
     */
    public int $websiteId;

    /**
     * The number of times the job may be attempted.
     * Set to 1 to disable retries - provision jobs should only run once.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param  Website  $website  The website model instance.
     */
    public function __construct(Website $website)
    {
        $this->websiteId = $website->id;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Note: backoff is kept for reference but won't be used with $tries = 1.
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    /**
     * Execute the job.
     *
     * This method now acts as a simple wrapper, calling the master provisioning command
     * to handle the orchestration. This adheres to the DRY principle, ensuring the
     * provisioning logic is defined in only one place.
     *
     * @throws Exception if the underlying Artisan command fails.
     */
    public function handle(): void
    {
        $this->queueMonitorLabel('Website #'.$this->websiteId);
        // Fetch the website - use withTrashed for consistency
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteProvision job failed: Website not found', [
                'website_id' => $this->websiteId,
            ]);

            return;
        }

        try {
            $website->ensureProvisioningRunStarted();

            Log::info('WebsiteProvision job started', [
                'website_id' => $website->id,
                'site_id' => $website->site_id,
                'domain' => $website->domain,
            ]);

            // Call the single, master orchestration command.
            // The command itself contains all the logic for the sequence of steps.
            Artisan::call('platform:provision-website', ['website_id' => $website->id]);

            // Reload website to check if the orchestrator paused for DNS verification.
            // When a step returns exit code 2 (WAITING), the orchestrator sets status
            // to WaitingForDns and returns SUCCESS — so we must NOT treat it as complete.
            $website->refresh();

            if ($website->status === WebsiteStatus::WaitingForDns) {
                Log::info('WebsiteProvision job paused — waiting for DNS verification', [
                    'website_id' => $website->id,
                ]);

                // Notify agency of the waiting state so it updates its local status
                SendAgencyWebhook::dispatchForWebsite($website, 'website.status_changed', [
                    'status' => WebsiteStatus::WaitingForDns->value,
                    'message' => 'Website provisioning paused — waiting for DNS verification.',
                ]);

                return;
            }

            // If the command completes without throwing an exception, it's a success.
            $this->onSuccess($website);

            Log::info('WebsiteProvision job completed', [
                'website_id' => $website->id,
            ]);
        } catch (Throwable $throwable) {
            Log::error('WebsiteProvision job failed', [
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            // Record activity log
            activity('Platform')
                ->performedOn($website)
                ->event('provision_failed')
                ->log('Website provisioning failed: '.$throwable->getMessage());

            // Re-throw the exception. Laravel's queue worker will catch it and
            // automatically call the failed() method.
            throw $throwable;
        }
    }

    /**
     * Handles the successful completion of the job.
     */
    public function onSuccess(Website $website): void
    {
        // Reload website from database to get latest state
        /** @var Website $website */
        $website = $website->fresh() ?: $website;

        // Sync website info from server
        $this->syncWebsite($website);
        $this->syncServer($website);
        $website->markProvisioningRunCompleted();

        $this->logActivity($website, ActivityAction::CREATE, $website->site_id.' website provisioned successfully.');

        // Notify platform user who created the website
        if ($website->createdBy) {
            $website->createdBy->notify(new WebsiteCreatedNotification($website));
        }

        // Send webhook to the agency
        SendAgencyWebhook::dispatchForWebsite($website, 'website.provisioned', [
            'status' => 'active',
            'message' => 'Website provisioned successfully.',
        ]);
    }

    /**
     * Handle a job failure.
     *
     * This method is automatically invoked by the queue worker when an exception is thrown
     * from the `handle()` method. It logs the failure and notifies the user.
     *
     * @param  Throwable  $exception  The exception that caused the job to fail.
     */
    public function failed(Throwable $exception): void
    {
        /** @var Website|null $website */
        $website = Website::withTrashed()->find($this->websiteId);

        if (! $website) {
            Log::error('WebsiteProvision failed() called but website not found', [
                'website_id' => $this->websiteId,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        // Reload website from database to get latest state
        /** @var Website $website */
        $website = $website->fresh() ?: $website;

        // Set status to failed
        $website->update(['status' => 'failed']);

        // Send webhook to the agency
        SendAgencyWebhook::dispatchForWebsite($website, 'website.provision_failed', [
            'status' => 'failed',
            'message' => 'Website provisioning failed.',
        ]);

        // Try to sync website info from server (the website may have been partially created)
        $this->syncWebsite($website);

        // Log the detailed error for debugging purposes.
        Log::error('WebsiteProvision failed for website #'.$website->id, [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Notify the user who initiated the action about the failure.
        // if ($website->createdBy) {
        //     $website->createdBy->notify(
        //         new WebsiteCreationFailedNotification($website, $exception->getMessage())
        //     );
        // }
    }

    /**
     * Sync website information from the server.
     * This is called both on success and failure to ensure the website record is up-to-date.
     */
    protected function syncWebsite(Website $website): void
    {
        $websiteService = resolve(WebsiteService::class);

        for ($attempt = 1; $attempt <= self::WEBSITE_SYNC_MAX_ATTEMPTS; $attempt++) {
            try {
                $result = $this->performWebsiteSyncAttempt($websiteService, $website);
            } catch (Throwable $e) {
                Log::warning('Failed to sync website after provisioning', [
                    'website_id' => $website->id,
                    'attempt' => $attempt,
                    'max_attempts' => self::WEBSITE_SYNC_MAX_ATTEMPTS,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::WEBSITE_SYNC_MAX_ATTEMPTS) {
                    $this->pauseBetweenWebsiteSyncAttempts();

                    continue;
                }

                break;
            }

            $website = $website->fresh() ?: $website;

            if ($this->hasWebsiteRuntimeMetadata($website)) {
                Log::info('Website synced successfully after provisioning', [
                    'website_id' => $website->id,
                    'attempt' => $attempt,
                    'result_message' => $result['message'] ?? null,
                ]);

                return;
            }

            Log::warning('Website sync completed but runtime metadata is still incomplete', [
                'website_id' => $website->id,
                'attempt' => $attempt,
                'max_attempts' => self::WEBSITE_SYNC_MAX_ATTEMPTS,
                'result_message' => $result['message'] ?? 'Unknown result',
            ]);

            if ($attempt < self::WEBSITE_SYNC_MAX_ATTEMPTS) {
                $this->pauseBetweenWebsiteSyncAttempts();
            }
        }

        $this->applyWebsiteVersionFallback($website);
    }

    protected function performWebsiteSyncAttempt(WebsiteService $websiteService, Website $website): array
    {
        return $websiteService->syncWebsiteInfo($website);
    }

    protected function pauseBetweenWebsiteSyncAttempts(): void
    {
        Sleep::sleep(self::WEBSITE_SYNC_RETRY_DELAY_SECONDS);
    }

    protected function hasWebsiteRuntimeMetadata(Website $website): bool
    {
        if (! empty($website->astero_version)) {
            return true;
        }

        if (! empty($website->getMetadata('laravel_version'))) {
            return true;
        }

        if (! empty($website->getMetadata('php_version'))) {
            return true;
        }

        if (! empty($website->getMetadata('app_env'))) {
            return true;
        }

        if ($website->getMetadata('app_debug') !== null) {
            return true;
        }

        if (! empty($website->getMetadata('queue_worker_status'))) {
            return true;
        }

        return ! empty($website->getMetadata('cron_status'));
    }

    protected function applyWebsiteVersionFallback(Website $website): void
    {
        if (! empty($website->astero_version)) {
            return;
        }

        $website->loadMissing('server');
        $serverVersion = $website->server?->astero_version;

        if (empty($serverVersion)) {
            return;
        }

        $website->astero_version = $serverVersion;

        if ($website->exists) {
            $website->save();
        }

        Log::info('Applied website version fallback from server after provisioning sync retries', [
            'website_id' => $website->id,
            'server_id' => $website->server->id,
            'astero_version' => $serverVersion,
        ]);
    }

    /**
     * Sync server information from Hestia to refresh domain usage counters.
     */
    protected function syncServer(Website $website): void
    {
        $website->loadMissing('server');
        $server = $website->server;

        if (! $server) {
            return;
        }

        try {
            $serverService = resolve(ServerService::class);
            $result = $serverService->syncServerInfo($server);

            if ($result['success'] ?? false) {
                Log::info('Server synced successfully after website provisioning', [
                    'server_id' => $server->id,
                    'website_id' => $website->id,
                ]);
            } else {
                Log::warning('Server sync returned non-success after website provisioning', [
                    'server_id' => $server->id,
                    'website_id' => $website->id,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (Throwable $throwable) {
            // Don't let sync failures prevent the provisioning flow from completing
            Log::warning('Failed to sync server after website provisioning', [
                'server_id' => $server->id,
                'website_id' => $website->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
