<?php

namespace Modules\Platform\Jobs;

use App\Traits\IsMonitored;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Domain;
use Throwable;

/**
 * Syncs WHOIS data for a domain in the background.
 *
 * This job is designed to be non-blocking - if WHOIS sync fails, it logs
 * the error but does not throw exceptions that would affect parent processes
 * like website provisioning.
 *
 * Dispatched during website provisioning after domain creation to fetch
 * and persist WHOIS information asynchronously.
 */
class DomainSyncWhois implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * The domain ID to sync WHOIS data for.
     */
    public int $domainId;

    /**
     * The number of times the job may be attempted.
     * Set to 1 to disable retries.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param  Domain  $domain  The domain model instance.
     */
    public function __construct(Domain $domain)
    {
        $this->domainId = $domain->id;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Note: backoff is kept for reference but won't be used with $tries = 1.
     */
    public function backoff(): array
    {
        return [60, 120];
    }

    /**
     * Execute the job.
     *
     * This method calls the sync command and gracefully handles failures
     * without propagating exceptions to parent processes.
     */
    public function handle(): void
    {
        $this->queueMonitorLabel('Domain #'.$this->domainId);
        $domain = Domain::withTrashed()->find($this->domainId);

        if (! $domain) {
            Log::warning('DomainSyncWhois job: Domain not found', [
                'domain_id' => $this->domainId,
            ]);

            return;
        }

        try {
            Log::info('DomainSyncWhois job started', [
                'domain_id' => $domain->id,
                'domain_name' => $domain->domain_name,
            ]);

            // Call the sync command
            $exitCode = Artisan::call('platform:sync-domain-whois', ['domain_id' => $domain->id]);

            if ($exitCode === 0) {
                Log::info('DomainSyncWhois job completed successfully', [
                    'domain_id' => $domain->id,
                    'domain_name' => $domain->domain_name,
                ]);
            } else {
                Log::warning('DomainSyncWhois job completed with warnings', [
                    'domain_id' => $domain->id,
                    'domain_name' => $domain->domain_name,
                    'exit_code' => $exitCode,
                ]);
            }
        } catch (Exception $exception) {
            // Log error but don't rethrow - this is a non-critical background task
            Log::error('DomainSyncWhois job failed', [
                'domain_id' => $domain->id,
                'domain_name' => $domain->domain_name,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is called when all retry attempts have been exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('DomainSyncWhois job failed after all retries', [
            'domain_id' => $this->domainId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
