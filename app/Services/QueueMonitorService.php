<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\QueueMonitorDefinition;
use App\Enums\MonitorStatus;
use App\Http\Resources\QueueMonitorResource;
use App\Models\Monitor;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class QueueMonitorService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        handleBulkAction as traitHandleBulkAction;
    }

    // ================================================================
    // SCAFFOLD WIRING
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new QueueMonitorDefinition;
    }

    protected function getResourceClass(): ?string
    {
        return QueueMonitorResource::class;
    }

    /**
     * Returns a standard paginator payload for the React datagrid.
     *
     * @return array<string, mixed>
     */
    public function getPaginatedMonitors(Request $request): array
    {
        $paginator = $this->getMonitorPaginator($request);
        $paginatedArray = $paginator->toArray();
        $paginatedArray['data'] = QueueMonitorResource::collection($paginator->items())
            ->resolve(request());

        return $paginatedArray;
    }

    // ================================================================
    // STATISTICS — maps integer status keys to string tab keys
    // ================================================================

    public function getStatistics(): array
    {
        $counts = Monitor::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => (int) array_sum($counts),
            'succeeded' => (int) ($counts[MonitorStatus::SUCCEEDED] ?? 0),
            'failed' => (int) ($counts[MonitorStatus::FAILED] ?? 0),
            'running' => (int) ($counts[MonitorStatus::RUNNING] ?? 0),
            'queued' => (int) ($counts[MonitorStatus::QUEUED] ?? 0),
            'stale' => (int) ($counts[MonitorStatus::STALE] ?? 0),
        ];
    }

    // ================================================================
    // STATUS FILTER — maps string tab keys to integer WHERE clause
    // ================================================================

    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->input('status') ?? 'all';

        match ($status) {
            'succeeded' => $query->where('status', MonitorStatus::SUCCEEDED),
            'failed' => $query->where('status', MonitorStatus::FAILED),
            'running' => $query->where('status', MonitorStatus::RUNNING),
            'queued' => $query->where('status', MonitorStatus::QUEUED),
            'stale' => $query->where('status', MonitorStatus::STALE),
            default => null, // 'all' — no filter
        };
    }

    // ================================================================
    // QUERY CUSTOMISATION
    // ================================================================

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        // Queue filter — driven by queue-stats table links or URL param.
        // Not a formal Filter (options are dynamic per-deployment), handled here.
        if ($queue = $request->input('queue')) {
            $query->where('queue', $queue);
        }
    }

    protected function applySorting(Builder $query, Request $request): void
    {
        $sortBy = (string) ($request->input('sort') ?? $request->input('sort_column') ?? $this->scaffold()->getDefaultSort());
        $sortOrder = strtolower((string) ($request->input('direction') ?? $request->input('sort_direction') ?? $this->scaffold()->getDefaultSortDirection())) === 'asc'
            ? 'asc'
            : 'desc';

        if ($sortBy === 'duration') {
            $query->orderByRaw(
                sprintf(
                    'EXTRACT(EPOCH FROM (COALESCE(finished_at, now()) - COALESCE(started_at, now()))) %s',
                    $sortOrder
                )
            );

            return;
        }

        if ($sortBy === 'wait') {
            $query->orderByRaw(
                sprintf(
                    'CASE WHEN queued_at IS NOT NULL AND started_at IS NOT NULL THEN EXTRACT(EPOCH FROM (started_at - queued_at)) ELSE NULL END %s NULLS LAST',
                    $sortOrder
                )
            );

            return;
        }

        $sortableColumns = $this->scaffold()->getSortableColumns();
        if (! in_array($sortBy, $sortableColumns, true)) {
            $sortBy = (string) $this->scaffold()->getDefaultSort();
        }

        $actualSortColumn = $this->scaffold()->getActualSortColumn($sortBy) ?? $sortBy;
        $query->orderBy($actualSortColumn, $sortOrder);
    }

    // ================================================================
    // FILTER OVERRIDE — handle metadata key+value pair
    // ================================================================

    protected function applyFilters(Builder $query, Request $request): void
    {
        // Both metadata_key and metadata_value must be present to apply the filter.
        $metaKey = trim((string) ($request->input('metadata_key') ?? ''));
        $metaValue = trim((string) ($request->input('metadata_value') ?? ''));

        if ($metaKey !== '' && $metaValue !== '') {
            // Using whereRaw with positional bindings intentionally: parameterizes both the key and
            // value (user input), which Laravel's JSON "column->key" syntax does not do for keys.
            // This app targets PostgreSQL exclusively; the ->> operator is correct here.
            $query->whereRaw('metadata->>? = ?', [$metaKey, $metaValue]);
        }

        // This definition has no other filters; metadata pair is fully handled above.
    }

    // ================================================================
    // QUEUE STATS — per-queue breakdown for dashboard section
    // ================================================================

    /**
     * Returns per-queue stats: real-time running/queued counts + historical succeeded/failed/avg-duration.
     *
     * @return array<int, array{queue: string, running: int, queued: int, succeeded: int, failed: int, avg_duration: float|null}>
     */
    public function getQueueStats(): array
    {
        $timeFrame = (int) (config('queue-monitor.ui.metrics_time_frame', 14));

        // Real-time counts — no time window
        $runningByQueue = Monitor::query()
            ->selectRaw('queue, COUNT(*) as cnt')
            ->where('status', MonitorStatus::RUNNING)
            ->groupBy('queue')
            ->pluck('cnt', 'queue');

        $queuedByQueue = Monitor::query()
            ->selectRaw('queue, COUNT(*) as cnt')
            ->where('status', MonitorStatus::QUEUED)
            ->groupBy('queue')
            ->pluck('cnt', 'queue');

        // Historical stats within the metrics time frame.
        // EXTRACT(EPOCH FROM ...) is PostgreSQL-specific — intentional, this app targets PostgreSQL exclusively.
        $historical = Monitor::query()
            ->selectRaw('queue, status, COUNT(*) as cnt, AVG(EXTRACT(EPOCH FROM (finished_at - started_at))) as avg_duration')
            ->whereIn('status', [MonitorStatus::SUCCEEDED, MonitorStatus::FAILED])
            ->where('started_at', '>=', Date::now()->subDays($timeFrame))
            ->groupBy('queue', 'status')
            ->get();

        $queueNames = collect()
            ->merge($runningByQueue->keys())
            ->merge($queuedByQueue->keys())
            ->merge($historical->pluck('queue'))
            ->unique()
            ->filter()
            ->sort()
            ->values();

        return $queueNames->map(function (string $name) use ($runningByQueue, $queuedByQueue, $historical): array {
            $histForQueue = $historical->where('queue', $name);
            $succeededRow = $histForQueue->firstWhere('status', MonitorStatus::SUCCEEDED);
            $failedRow = $histForQueue->firstWhere('status', MonitorStatus::FAILED);

            return [
                'queue' => $name,
                'running' => (int) ($runningByQueue[$name] ?? 0),
                'queued' => (int) ($queuedByQueue[$name] ?? 0),
                'succeeded' => $succeededRow ? (int) $succeededRow->cnt : 0,
                'failed' => $failedRow ? (int) $failedRow->cnt : 0,
                'avg_duration' => $succeededRow && $succeededRow->avg_duration !== null
                    ? round((float) $succeededRow->avg_duration, 1)
                    : null,
            ];
        })->all();
    }

    // ================================================================
    // CHART DATA — hourly snapshots for throughput charts
    // ================================================================

    /**
     * Returns the last N hours of snapshot data structured for Chart.js.
     *
     * @return array{labels: list<string>, queues: list<string>, datasets: array<string, array{succeeded: list<int>, failed: list<int>}>}
     */
    public function getChartData(int $hours = 24): array
    {
        $start = Date::now()->subHours($hours)->startOfHour();

        $rows = DB::table('queue_monitor_snapshots')
            ->where('period_start', '>=', $start)
            ->orderBy('period_start')
            ->get(['period_start', 'queue', 'succeeded', 'failed']);

        if ($rows->isEmpty()) {
            return ['labels' => [], 'queues' => [], 'datasets' => []];
        }

        // Build list of hourly labels
        $labels = [];
        $slots = [];
        for ($i = 0; $i < $hours; $i++) {
            $slot = $start->copy()->addHours($i);
            $key = $slot->format('Y-m-d H:00');
            $labels[] = $slot->format('M j H:00');
            $slots[] = $key;
        }

        $queues = $rows->pluck('queue')->unique()->sort()->values()->all();

        /** @var array<string, array{succeeded: list<int>, failed: list<int>}> $datasets */
        $datasets = [];
        foreach ($queues as $queue) {
            $datasets[$queue] = [
                'succeeded' => array_fill(0, $hours, 0),
                'failed' => array_fill(0, $hours, 0),
            ];
        }

        foreach ($rows as $row) {
            $slotKey = Date::parse($row->period_start)->format('Y-m-d H:00');
            $idx = array_search($slotKey, $slots, true);
            if ($idx !== false && isset($datasets[$row->queue])) {
                $datasets[$row->queue]['succeeded'][$idx] = (int) $row->succeeded;
                $datasets[$row->queue]['failed'][$idx] = (int) $row->failed;
            }
        }

        return ['labels' => $labels, 'queues' => $queues, 'datasets' => $datasets];
    }

    // ================================================================
    // BULK ACTIONS — intercept 'purge' before the trait checks ids
    // ================================================================

    public function handleBulkAction(Request $request): array
    {
        if ($request->input('action') === 'purge') {
            $count = Monitor::query()->count();
            Monitor::query()->delete();

            $noun = $count === 1 ? 'entry' : 'entries';

            return [
                'success' => true,
                'message' => sprintf('Purged %d monitor %s.', $count, $noun),
                'affected' => $count,
            ];
        }

        return $this->traitHandleBulkAction($request);
    }

    private function getMonitorPaginator(Request $request): LengthAwarePaginator
    {
        return $this->buildListQuery($request)
            ->paginate($this->getPerPage($request))
            ->onEachSide(1);
    }
}
