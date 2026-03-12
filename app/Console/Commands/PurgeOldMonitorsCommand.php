<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HandlesDateInputs;
use App\Enums\MonitorStatus;
use App\Services\QueueMonitor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurgeOldMonitorsCommand extends Command
{
    use HandlesDateInputs;

    protected $signature = 'queue-monitor:purge {--before=} {--beforeDays=} {--beforeInterval=} {--only-succeeded} {--queue=} {--dry} {--chunk}';

    public function handle(): int
    {
        $beforeDate = self::parseBeforeDate($this);
        if (! $beforeDate instanceof Carbon) {
            $this->error('Needs at least --before or --beforeDays arguments');

            return 1;
        }

        $query = QueueMonitor::getModel()
            ->newQuery()
            ->where(fn (Builder $query) => $query
                ->where('queued_at', '<', $beforeDate)
                ->orWhere('started_at', '<', $beforeDate)
            );

        $queues = array_filter(explode(',', $this->option('queue') ?? ''));

        if ($queues !== []) {
            $query->whereIn('queue', array_map(trim(...), $queues));
        }

        if ($this->option('only-succeeded')) {
            $query->where('status', '=', MonitorStatus::SUCCEEDED);
        }

        $count = $query->count();

        $this->info(
            sprintf('Purging %d jobs before %s.', $count, $beforeDate->format('Y-m-d H:i:s'))
        );

        if ($this->option('chunk')) {
            $query->chunkById(200, function (Collection $models, int $page) use ($count): void {
                $this->info(
                    sprintf('Deleted chunk %d / %d', $page, ceil($count / 200))
                );

                if ($this->option('dry')) {
                    return;
                }

                QueueMonitor::getModel()
                    ->newQuery()
                    ->whereIn('id', $models->pluck('id'))
                    ->delete();
            });
        } else {
            if (! $this->option('dry')) {
                $query->delete();
            }

            $this->info(
                sprintf('Deleted %d jobs before %s.', $count, $beforeDate->format('Y-m-d H:i:s'))
            );
        }

        return 0;
    }
}
