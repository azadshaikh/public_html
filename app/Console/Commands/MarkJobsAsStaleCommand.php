<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HandlesDateInputs;
use App\Enums\MonitorStatus;
use App\Services\QueueMonitor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class MarkJobsAsStaleCommand extends Command
{
    use HandlesDateInputs;

    protected $signature = 'queue-monitor:stale {--before=} {--beforeDays=} {--beforeInterval=} {--dry}';

    public function handle(): int
    {
        $beforeDate = self::parseBeforeDate($this);
        if (! $beforeDate instanceof Carbon) {
            $this->error('Needs at least --before or --beforeDays arguments');

            return 1;
        }

        $query = QueueMonitor::getModel()
            ->newQuery()
            ->where('status', MonitorStatus::RUNNING)
            ->where('started_at', '<', $beforeDate);

        $this->info(
            sprintf('Marking %d jobs after %s as stale', $count = $query->count(), $beforeDate->format('Y-m-d H:i:s'))
        );

        $query->chunk(500, function (Collection $models, int $page) use ($count): void {
            $this->info(
                sprintf('Marked chunk %d / %d as stale', $page, ceil($count / 500))
            );

            if ($this->option('dry')) {
                return;
            }

            QueueMonitor::getModel()
                ->newQuery()
                ->whereIn('id', $models->pluck('id'))
                ->update([
                    'status' => MonitorStatus::STALE,
                ]);
        });

        return 0;
    }
}
