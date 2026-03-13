<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\ActivityLogDefinition;
use App\Enums\ActivityAction;
use App\Http\Resources\ActivityLogsResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ActivityLogService
 *
 * Scaffold-based service for managing activity logs with DataGrid support.
 */
class ActivityLogService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED SCAFFOLD METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new ActivityLogDefinition;
    }

    // ================================================================
    // PAGINATED DATA (Inertia/Datagrid format)
    // ================================================================

    public function getPaginatedLogs(Request $request): array
    {
        $query = $this->buildListQuery($request);
        $paginator = $query->paginate($this->getPerPage($request))->onEachSide(1);

        $paginatedArray = $paginator->toArray();
        $paginatedArray['data'] = ActivityLogsResource::collection($paginator->items())
            ->resolve(request());

        return $paginatedArray;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        // Apply visibility scope to hide super user activities from statistics for non-super users
        return [
            'total' => ActivityLog::visibleToCurrentUser()->whereNull('deleted_at')->count(),
            'today' => ActivityLog::visibleToCurrentUser()->whereNull('deleted_at')
                ->whereDate('created_at', today())
                ->count(),
            'this_week' => ActivityLog::visibleToCurrentUser()->whereNull('deleted_at')
                ->where('created_at', '>=', now()->startOfWeek())
                ->count(),
            'this_month' => ActivityLog::visibleToCurrentUser()->whereNull('deleted_at')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'trash' => ActivityLog::visibleToCurrentUser()->onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // FILTER OPTIONS
    // ================================================================

    public function getEventOptions(): array
    {
        $options = [['value' => '', 'label' => 'All Actions']];

        foreach (ActivityAction::cases() as $action) {
            $options[] = [
                'value' => $action->value,
                'label' => ucwords(str_replace('_', ' ', $action->value)),
            ];
        }

        return $options;
    }

    public function getUserOptions(): array
    {
        $options = [['value' => '', 'label' => 'All Users']];

        // Apply visibility scope to hide super users from the filter dropdown
        $users = User::visibleToCurrentUser()
            ->select('id', 'first_name', 'last_name', 'email')
            ->whereHas('activityLogs')
            ->orderBy('first_name')
            ->limit(50)
            ->get();

        foreach ($users as $user) {
            $options[] = [
                'value' => (string) $user->id,
                'label' => $user->name.' ('.$user->email.')',
            ];
        }

        return $options;
    }

    // ================================================================
    // OVERRIDE: Skip audit fields (activity_log table has no deleted_by/updated_by)
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
    // CLEANUP & EXPORT (Keep existing functionality)
    // ================================================================

    /**
     * Clean up old activity logs
     */
    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        return ActivityLog::query()->where('created_at', '<', now()->subDays($daysToKeep))
            ->forceDelete();
    }

    /**
     * Export activities to array format
     */
    public function exportActivities(array $filters = []): array
    {
        // Apply visibility scope to hide super user activities from exports for non-super users
        /** @var Collection<int, ActivityLog> $activities */
        $activities = ActivityLog::visibleToCurrentUser()
            ->with(['causer', 'subject'])
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->where('description', 'ilike', sprintf('%%%s%%', $search)))
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($filters['causer_id'] ?? null, fn ($q, $userId) => $q->where('causer_id', $userId))->latest()
            ->limit($filters['limit'] ?? 1000)
            ->get();

        return $activities->map(function (ActivityLog $activity): array {
            $causerName = data_get($activity->causer, 'name');

            return [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'user' => is_string($causerName) && $causerName !== '' ? $causerName : 'System',
                'subject' => $activity->subject_display,
                'ip_address' => $activity->ip_address,
                'created_at' => $activity->created_at->toISOString(),
                'properties' => $activity->properties,
            ];
        })->all();
    }

    protected function getResourceClass(): ?string
    {
        return ActivityLogsResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'causer:id,first_name,last_name,email',
            'subject',
        ];
    }

    // ================================================================
    // QUERY BUILDING (Status Tab Support)
    // ================================================================

    protected function buildListQuery(Request $request): Builder
    {
        $query = ActivityLog::query();

        // Apply visibility scope to hide super user activities from non-super users
        $query->visibleToCurrentUser();

        // Filter out records with invalid morph types (deleted models)
        $query->withValidRelations();

        // Get status from request or route
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Handle status filtering with soft deletes
        if ($status === 'trash') {
            $query->onlyTrashed();
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
        // Event/Action filter
        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        // User/Causer filter
        if ($causerId = $request->input('causer_id')) {
            $query->where('causer_id', $causerId);
        }

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
            'icon' => 'ri-history-line',
            'title' => 'No Activity Logs Found',
            'message' => 'Activity logs will appear here once actions are performed in the system.',
            'showAddButton' => false,
        ];
    }
}
