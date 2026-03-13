<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\NotFoundLogDefinition;
use App\Http\Resources\NotFoundLogResource;
use App\Models\NotFoundLog;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * NotFoundLogService
 *
 * Scaffold-based service for managing 404 logs with DataGrid support.
 */
class NotFoundLogService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED SCAFFOLD METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new NotFoundLogDefinition;
    }

    // ================================================================
    // PAGINATED DATA (Inertia/Datagrid format)
    // ================================================================

    public function getPaginatedLogs(Request $request): array
    {
        $query = $this->buildListQuery($request);
        $paginator = $query->paginate($this->getPerPage($request))->onEachSide(1);

        $paginatedArray = $paginator->toArray();
        $paginatedArray['data'] = NotFoundLogResource::collection($paginator->items())
            ->resolve(request());

        return $paginatedArray;
    }

    // ================================================================
    // STATISTICS
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => NotFoundLog::visibleToCurrentUser()->whereNull('deleted_at')->count(),
            'suspicious' => NotFoundLog::visibleToCurrentUser()->where('is_suspicious', true)
                ->whereNull('deleted_at')->count(),
            // Bots that are NOT suspicious (exclusive with suspicious tab)
            'bots' => NotFoundLog::visibleToCurrentUser()->where('is_bot', true)
                ->where('is_suspicious', false)->whereNull('deleted_at')->count(),
            // Human (non-bot) that are NOT suspicious (exclusive with suspicious tab)
            'human' => NotFoundLog::visibleToCurrentUser()->where('is_bot', false)
                ->where('is_suspicious', false)->whereNull('deleted_at')->count(),
            'today' => NotFoundLog::visibleToCurrentUser()->whereDate('created_at', today())
                ->whereNull('deleted_at')->count(),
            'trash' => NotFoundLog::visibleToCurrentUser()->onlyTrashed()->count(),
        ];
    }

    /**
     * Get extended statistics for dashboard.
     */
    public function getExtendedStatistics(int $days = 30): array
    {
        $baseQuery = NotFoundLog::visibleToCurrentUser()
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays($days));

        return [
            'total' => (clone $baseQuery)->count(),
            'unique_urls' => (clone $baseQuery)->distinct('url')->count('url'),
            'unique_ips' => (clone $baseQuery)->distinct('ip_address')->count('ip_address'),
            'suspicious' => (clone $baseQuery)->where('is_suspicious', true)->count(),
            // Bots that are NOT suspicious (exclusive)
            'bots' => (clone $baseQuery)->where('is_bot', true)->where('is_suspicious', false)->count(),
            // Human (non-bot) that are NOT suspicious (exclusive)
            'human' => (clone $baseQuery)->where('is_bot', false)->where('is_suspicious', false)->count(),
            'top_urls' => NotFoundLog::getTopMissingUrls(5, $days),
            'top_referers' => NotFoundLog::getTopReferers(5, $days),
            'top_ips' => NotFoundLog::getTopIps(5, min($days, 7)),
        ];
    }

    /**
     * Get recent activity stats for the show page.
     */
    public function getRecentActivityStats(NotFoundLog $notFoundLog, int $minutes = 1440): array
    {
        $baseUrl = NotFoundLog::query()
            ->visibleToCurrentUser()
            ->where('url', $notFoundLog->url)
            ->recent($minutes);

        $baseIp = NotFoundLog::query()
            ->visibleToCurrentUser()
            ->where('ip_address', $notFoundLog->ip_address)
            ->recent($minutes);

        return [
            'url' => [
                'total' => (clone $baseUrl)->count(),
                'suspicious' => (clone $baseUrl)->where('is_suspicious', true)->count(),
                'unique_ips' => (clone $baseUrl)->distinct('ip_address')->count('ip_address'),
            ],
            'ip' => [
                'total' => (clone $baseIp)->count(),
                'suspicious' => (clone $baseIp)->where('is_suspicious', true)->count(),
                'unique_urls' => (clone $baseIp)->distinct('url')->count('url'),
            ],
        ];
    }

    // ================================================================
    // OVERRIDE: Skip audit fields (not_found_logs table has no deleted_by/updated_by)
    // ================================================================

    public function delete(Model $model): void
    {
        DB::transaction(function () use ($model): void {
            $this->beforeDelete($model);
            $model->delete();
            $this->afterDelete($model);
        });
    }

    public function restore(int|string $id): Model
    {
        return DB::transaction(function () use ($id) {
            $modelClass = $this->getModelClass();
            $model = $modelClass::withTrashed()->findOrFail($id);

            $model->restore();
            $this->afterRestore($model);

            return $model->fresh();
        });
    }

    // ================================================================
    // CLEANUP FUNCTIONALITY
    // ================================================================

    /**
     * Clean up old 404 logs
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        return NotFoundLog::query()->where('created_at', '<', now()->subDays($daysToKeep))
            ->forceDelete();
    }

    protected function getResourceClass(): ?string
    {
        return NotFoundLogResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'user:id,first_name,last_name,email',
        ];
    }

    // ================================================================
    // QUERY BUILDING (Status Tab Support)
    // ================================================================

    protected function buildListQuery(Request $request): Builder
    {
        $query = NotFoundLog::query();

        // Apply visibility scope to hide super user 404 logs from non-super users
        $query->visibleToCurrentUser();

        // Get status from request or route
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Handle status filtering (tabs are mutually exclusive)
        // NOTE: Bots/Human tabs exclude suspicious records to prevent overlap.
        // This means a suspicious bot appears ONLY in "Suspicious" tab, not "Bots" tab.
        if ($status === 'trash') {
            $query->onlyTrashed();
        } elseif ($status === 'suspicious') {
            $query->where('is_suspicious', true)->whereNull('deleted_at');
        } elseif ($status === 'bots') {
            // Bots that are NOT suspicious (exclusive tab)
            $query->where('is_bot', true)->where('is_suspicious', false)->whereNull('deleted_at');
        } elseif ($status === 'human') {
            // Human (non-bot) that are NOT suspicious (exclusive tab)
            $query->where('is_bot', false)->where('is_suspicious', false)->whereNull('deleted_at');
        } else {
            // 'all' - only non-deleted
            $query->whereNull('deleted_at');
        }

        // Merge route status into request for filters
        if (! $request->has('status') && $request->route('status')) {
            $request->merge(['status' => $status]);
        }

        // Apply standard scaffold methods
        $this->applyEagerLoading($query);
        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);
        $this->customizeListQuery($query, $request);

        return $query;
    }

    // ================================================================
    // CUSTOM FILTER HANDLING
    // ================================================================

    protected function applyFilters(Builder $query, Request $request): void
    {
        // Date range filter
        if ($from = $request->input('created_at_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('created_at_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    // ================================================================
    // EMPTY STATE CONFIGURATION
    // ================================================================

    protected function getEmptyStateConfig(): array
    {
        return [
            'icon' => 'ri-file-warning-line',
            'title' => 'No 404 Logs Found',
            'message' => '404 errors will appear here when visitors try to access non-existent pages.',
            'showAddButton' => false,
        ];
    }
}
