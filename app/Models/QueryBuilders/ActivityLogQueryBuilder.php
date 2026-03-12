<?php

namespace App\Models\QueryBuilders;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ActivityLogQueryBuilder
 *
 * Enhanced query builder for ActivityLog model with improved filtering,
 * searching, and pagination capabilities following Laravel 12 best practices.
 */
class ActivityLogQueryBuilder extends Builder
{
    /**
     * Include trashed records in query results.
     */
    public function withTrashed(bool $withTrashed = true): self
    {
        if (! $withTrashed) {
            return $this->withoutTrashed();
        }

        return $this->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Exclude trashed records from query results.
     */
    public function withoutTrashed(): self
    {
        /** @var ActivityLog $model */
        $model = $this->getModel();

        return $this
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNull($model->getQualifiedDeletedAtColumn());
    }

    /**
     * Return only trashed records.
     */
    public function onlyTrashed(): self
    {
        /** @var ActivityLog $model */
        $model = $this->getModel();

        return $this
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($model->getQualifiedDeletedAtColumn());
    }

    /**
     * Search activities by log name, description, or event
     */
    public function search(?string $search): self
    {
        if (! $search) {
            return $this;
        }

        return $this->where(function (Builder $query) use ($search): void {
            $query->where('log_name', 'ilike', sprintf('%%%s%%', $search))
                ->orWhere('description', 'ilike', sprintf('%%%s%%', $search))
                ->orWhere('event', 'ilike', sprintf('%%%s%%', $search));
        });
    }

    /**
     * Filter by specific action/event
     */
    public function filterByAction(ActivityAction|string|null $action): self
    {
        if ($action === null) {
            return $this;
        }

        $actionValue = $action instanceof ActivityAction ? $action->value : $action;

        return $this->where('event', $actionValue);
    }

    /**
     * Filter by multiple actions
     */
    public function filterByActions(array $actions): self
    {
        if ($actions === []) {
            return $this;
        }

        $actionValues = collect($actions)->map(
            fn ($action) => $action instanceof ActivityAction ? $action->value : $action
        )->toArray();

        return $this->whereIn('event', $actionValues);
    }

    /**
     * Filter by date range
     */
    public function filterByDate(?array $date): self
    {
        if (! $date) {
            return $this;
        }

        if (isset($date['from']) && $date['from']) {
            $this->whereDate('created_at', '>=', $date['from']);
        }

        if (isset($date['to']) && $date['to']) {
            $this->whereDate('created_at', '<=', $date['to']);
        }

        return $this;
    }

    /**
     * Backward-compatible alias for date range filtering.
     */
    public function byDateRange(?string $from = null, ?string $to = null): self
    {
        return $this->filterByDate(['from' => $from, 'to' => $to]);
    }

    /**
     * Filter by causer (user who performed the action)
     */
    public function filterByCreator(string|array|int|null $creatorIds): self
    {
        if (! $creatorIds) {
            return $this;
        }

        if (is_array($creatorIds)) {
            return $this->whereIn('causer_id', $creatorIds);
        }

        return $this->where('causer_id', $creatorIds);
    }

    /**
     * Backward-compatible alias for filtering by causer ID.
     */
    public function byCauser(int|string|null $causerId): self
    {
        return $this->filterByCreator($causerId);
    }

    /**
     * Filter by subject type (model class)
     */
    public function filterBySubjectType(?string $subjectType): self
    {
        if (! $subjectType) {
            return $this;
        }

        return $this->where('subject_type', $subjectType);
    }

    /**
     * Backward-compatible alias for filtering by subject type.
     */
    public function bySubjectType(?string $subjectType): self
    {
        return $this->filterBySubjectType($subjectType);
    }

    /**
     * Filter by subject ID
     */
    public function filterBySubjectId(int|string|null $subjectId): self
    {
        if (! $subjectId) {
            return $this;
        }

        return $this->where('subject_id', $subjectId);
    }

    /**
     * Filter activities from the last N days
     */
    public function recent(int $days = 7): self
    {
        return $this->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Filter out system logs and super-user activities for non-super users.
     */
    public function visibleToCurrentUser(): self
    {
        $currentUser = Auth::user();

        if (! $currentUser || $currentUser->isSuperUser()) {
            return $this;
        }

        $superUserIds = DB::table('model_has_roles')
            ->where('role_id', User::superUserRoleId())
            ->where('model_type', User::class)
            ->pluck('model_id');

        return $this
            ->whereNotNull('causer_id')
            ->whereNotIn('causer_id', $superUserIds);
    }

    /**
     * Filter activities from today
     */
    public function today(): self
    {
        return $this->whereDate('created_at', today());
    }

    /**
     * Filter activities from this week
     */
    public function thisWeek(): self
    {
        return $this->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Filter activities from this month
     */
    public function thisMonth(): self
    {
        return $this->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    /**
     * Apply sorting based on predefined options
     */
    public function filterBySortable(?string $sortable): self
    {
        if (! $sortable) {
            return $this;
        }

        return match ($sortable) {
            'latest' => $this->latest(),
            'oldest' => $this->oldest(),
            'latest_updated' => $this->latest('updated_at'),
            'oldest_updated' => $this->oldest('updated_at'),
            'event_asc' => $this->orderBy('event', 'ASC'),
            'event_desc' => $this->orderBy('event', 'DESC'),
            default => $this,
        };
    }

    /**
     * Sort by specific column and direction
     */
    public function sortBy(?string $column, string $direction = 'desc'): self
    {
        if (! $column) {
            return $this;
        }

        $allowedColumns = ['created_at', 'updated_at', 'event', 'log_name', 'description'];
        $allowedDirections = ['asc', 'desc'];

        if (! in_array($column, $allowedColumns) || ! in_array(strtolower($direction), $allowedDirections)) {
            return $this;
        }

        return $this->orderBy($column, $direction);
    }

    /**
     * Apply multiple order clauses
     */
    public function orderResults(string|array|null $order): self
    {
        if (! $order) {
            return $this;
        }

        if (is_string($order)) {
            return $this->orderBy('created_at', $order);
        }

        foreach ($order as $column => $direction) {
            $this->orderBy($column, $direction);
        }

        return $this;
    }

    /**
     * Enhanced pagination with better defaults and validation
     */
    public function paginateResults(?array $pagination = null): LengthAwarePaginator
    {
        $perPage = $pagination['limit'] ?? $pagination['per_page'] ?? 15;
        $page = $pagination['page'] ?? 1;

        // Validate and sanitize pagination parameters
        $perPage = max(1, min(100, (int) $perPage)); // Between 1 and 100
        $page = max(1, (int) $page); // At least 1

        return $this->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get activities with their related models eagerly loaded
     * Only loads causer to avoid issues with file-based models like Theme
     */
    public function withRelations(): self
    {
        // Only load causer relationship
        // Subject relationship will be loaded lazily with withDefault() protection
        return $this->with(['causer']);
    }

    /**
     * Filter out records with invalid morph types.
     */
    public function withValidRelations(): self
    {
        $validCauserTypes = ActivityLog::getValidMorphTypes('causer_type');
        $validSubjectTypes = ActivityLog::getValidMorphTypes('subject_type');

        return $this->where(function ($query) use ($validCauserTypes, $validSubjectTypes): void {
            $query->where(function ($q) use ($validCauserTypes): void {
                $q->whereNull('causer_type')
                    ->orWhereIn('causer_type', $validCauserTypes);
            })->where(function ($q) use ($validSubjectTypes): void {
                $q->whereNull('subject_type')
                    ->orWhereIn('subject_type', $validSubjectTypes);
            });
        });
    }

    /**
     * Get activities for a specific model instance
     */
    public function forModel(string $modelType, int $modelId): self
    {
        return $this->where('subject_type', $modelType)
            ->where('subject_id', $modelId);
    }

    /**
     * Get activities by specific user
     */
    public function byUser(int $userId): self
    {
        return $this->where('causer_id', $userId);
    }

    /**
     * Get activities with specific property
     */
    public function withProperty(string $key, mixed $value = null): self
    {
        if ($value === null) {
            return $this->whereJsonContainsKey('properties', $key);
        }

        return $this->whereJsonContains('properties->'.$key, $value);
    }

    /**
     * Exclude system-generated activities
     */
    public function excludeSystem(): self
    {
        return $this->whereNotNull('causer_id');
    }

    /**
     * Only system-generated activities
     */
    public function onlySystem(): self
    {
        return $this->whereNull('causer_id');
    }
}
