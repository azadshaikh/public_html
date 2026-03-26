<?php

namespace Modules\Platform\Jobs\Concerns;

use Exception;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;

trait InteractsWithWebsiteLifecycleExecution
{
    public int $timeout = 300;

    public bool $failOnTimeout = true;

    /**
     * Allow overlap retries while the active lifecycle job finishes or is cancelled.
     */
    public int $tries = 10;

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->websiteLifecycleLockKey()))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 60),
        ];
    }

    protected function initializeLifecycleMonitor(string $label): void
    {
        $this->queueMonitorLabel($label);
        $this->queueMonitorMetadata([
            'website_id' => $this->websiteId,
            'cancellable' => true,
            'lifecycle_job' => static::class,
            'lifecycle_lock_key' => $this->websiteLifecycleLockKey(),
        ]);
    }

    protected function throwIfLifecycleCancellationRequested(string $jobLabel, string $step): void
    {
        $cancelRequestedAt = $this->queueMonitorMetadataValue('cancel_requested_at');

        if (! filled($cancelRequestedAt)) {
            return;
        }

        Log::warning($jobLabel.': cancellation requested', [
            'website_id' => $this->websiteId,
            'step' => $step,
            'cancel_requested_at' => $cancelRequestedAt,
        ]);

        throw new RuntimeException($jobLabel.' cancelled by user before '.$step.'.');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function callLifecycleArtisanStep(string $jobLabel, string $label, string $command, array $arguments): void
    {
        $this->throwIfLifecycleCancellationRequested($jobLabel, $label);

        $startedAt = microtime(true);

        Log::info($jobLabel.': step started', [
            'website_id' => $this->websiteId,
            'step' => $label,
            'command' => $command,
        ]);

        $exitCode = Artisan::call($command, $arguments);
        $output = trim(Artisan::output());
        $duration = round(microtime(true) - $startedAt, 2);

        Log::info($jobLabel.': step finished', [
            'website_id' => $this->websiteId,
            'step' => $label,
            'command' => $command,
            'exit_code' => $exitCode,
            'duration_seconds' => $duration,
            'output' => $output,
        ]);

        if ($exitCode !== 0) {
            throw new Exception(sprintf(
                '%s failed with exit code %d%s',
                $label,
                $exitCode,
                $output !== '' ? ': '.$output : '.'
            ));
        }
    }

    protected function websiteLifecycleLockKey(): string
    {
        return 'website-lifecycle:'.$this->websiteId;
    }
}
