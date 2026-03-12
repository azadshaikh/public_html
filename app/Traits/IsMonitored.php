<?php

namespace App\Traits;

use App\Models\Contracts\MonitorContract;
use App\Services\QueueMonitor;
use Illuminate\Queue\InteractsWithQueue;

/**
 * @mixin InteractsWithQueue
 */
trait IsMonitored
{
    /**
     * The unix timestamp explaining the last time a progress has been written to database.
     *
     * @var int|null
     */
    private $progressLastUpdated;

    /**
     * Internal variable used for tracking chunking progress.
     *
     * @var int
     */
    private $progressCurrentChunk = 0;

    /**
     * Update progress.
     *
     * @param  int  $progress  Progress as integer 0-100
     */
    public function queueProgress(int $progress): void
    {
        $progress = min(100, max(0, $progress));

        if ($this->isQueueProgressOnCooldown($progress)) {
            return;
        }

        if (! $monitor = $this->getQueueMonitor()) {
            return;
        }

        $monitor->update([
            'progress' => $progress,
        ]);

        $this->progressLastUpdated = time();
    }

    /**
     * Automatically update the current progress in each chunk iteration.
     *
     * @param  int  $collectionCount  The total collection item amount
     * @param  int  $perChunk  The size of each chunk
     */
    public function queueProgressChunk(int $collectionCount, int $perChunk): void
    {
        $this->queueProgress(
            ++$this->progressCurrentChunk * $perChunk / $collectionCount * 100
        );
    }

    /**
     * Set Monitor data.
     *
     * @param  array  $data  Custom data
     * @param  bool  $merge  Merge the data instead of overriding
     */
    public function queueData(array $data, bool $merge = false): void
    {
        if (! $monitor = $this->getQueueMonitor()) {
            return;
        }

        if ($merge) {
            $data = array_merge($monitor->getData(), $data);
        }

        $monitor->update([
            'data' => json_encode($data),
        ]);
    }

    /**
     * Set a human-readable label for this job in the queue monitor.
     * Opt-in: call from handle() to display model context, e.g. "Website #42".
     */
    public function queueMonitorLabel(string $label): void
    {
        if (! $monitor = $this->getQueueMonitor()) {
            return;
        }

        $monitor->update([
            'metadata' => array_merge($monitor->metadata ?? [], ['_label' => $label]),
        ]);
    }

    /**
     * Check if the monitor should skip writing the progress to database avoiding rapid update queries.
     * The progress values 0, 25, 50, 75 and 100 will always be written.
     */
    private function isQueueProgressOnCooldown(int $progress): bool
    {
        if (in_array($progress, [0, 25, 50, 75, 100])) {
            return false;
        }

        if ($this->progressLastUpdated === null) {
            return false;
        }

        return time() - $this->progressLastUpdated < $this->progressCooldown();
    }

    /**
     * Delete Queue Monitor object.
     */
    protected function deleteQueueMonitor(): void
    {
        if (! $monitor = $this->getQueueMonitor()) {
            return;
        }

        $monitor->delete();
    }

    /**
     * Return Queue Monitor Model.
     */
    protected function getQueueMonitor(): ?MonitorContract
    {
        if (! property_exists($this, 'job')) { // @phpstan-ignore function.alreadyNarrowedType
            return null;
        }

        if (! $this->job) {
            return null;
        }

        $jobId = QueueMonitor::getJobId($this->job);

        $model = QueueMonitor::getModel();

        return $model::whereJob($jobId)
            ->orderBy('started_at', 'desc')
            ->first();
    }

    /**
     * Whether to keep successful monitor models. This can be used if you only want to keep
     * failed monitors for jobs that are frequently executed but worth to monitor. You are free
     * to use the Laravel built-in failed job procedures.
     */
    public static function keepMonitorOnSuccess(): bool
    {
        return true;
    }

    /**
     * The time in seconds to wait before a following queue progress update will be issued.
     * This is used to avoid writing many progress updates to the database. 0 = no delay.
     */
    public function progressCooldown(): int
    {
        return 0;
    }
}
