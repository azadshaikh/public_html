<?php

declare(strict_types=1);

namespace App\Traits;

use App\Scaffold\Action;
use App\Scaffold\ScaffoldDefinition;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * Scaffoldable - Trait for services using scaffold definitions
 *
 * This trait provides the core CRUD functionality based on a ScaffoldDefinition.
 * It handles listing, filtering, searching, sorting, bulk actions, and CRUD operations.
 *
 * Returns DataGrid API format:
 * {
 *     items: [...],
 *     pagination: { current_page, last_page, per_page, total, from, to },
 *     columns: [...],
 *     filters: [...],
 *     bulk_actions: [...],
 *     statistics: { total, ... },
 *     empty_state_config: { ... }
 * }
 *
 * @example
 * class AddressService
 * {
 *     use Scaffoldable;
 *
 *     public function getScaffoldDefinition(): ScaffoldDefinition
 *     {
 *         return new AddressDefinition();
 *     }
 * }
 */
trait Scaffoldable
{
    /**
     * Cache for Schema::hasColumn results.
     *
     * @var array<string, bool>
     */
    protected static array $schemaHasColumnCache = [];

    /**
     * Cached scaffold definition
     */
    protected ?ScaffoldDefinition $scaffoldDefinitionCache = null;

    /**
     * Get the scaffold definition for this service
     */
    abstract public function getScaffoldDefinition(): ScaffoldDefinition;

    /**
     * Get scaffold definition (cached)
     */
    public function scaffold(): ScaffoldDefinition
    {
        if ($this->scaffoldDefinitionCache === null) {
            $this->scaffoldDefinitionCache = $this->getScaffoldDefinition();
        }

        return $this->scaffoldDefinitionCache;
    }

    /**
     * Get model class from scaffold
     */
    public function getModelClass(): string
    {
        return $this->scaffold()->getModelClass();
    }

    /**
     * Get entity name from scaffold
     */
    public function getEntityName(): string
    {
        return $this->scaffold()->getEntityName();
    }

    /**
     * Get entity plural name from scaffold
     */
    public function getEntityPlural(): string
    {
        return $this->scaffold()->getEntityPlural();
    }

    // =========================================================================
    // DATA RETRIEVAL - DataGrid API Format
    // =========================================================================

    /**
     * Get paginated list data in Datagrid-compatible format.
     *
     * Response structure (PaginatedData-compatible):
     * - rows: PaginatedData with transformed items in `data`, plus pagination links
     * - filters: Current request filter/sort/pagination values (for Datagrid state)
     * - statistics: Status counts and totals (only on initial load or when include_stats=1)
     * - empty_state_config: Empty state display config
     *
     * Performance: Statistics are expensive (multiple COUNT queries). By default,
     * they're included on initial page load but skipped on subsequent AJAX requests
     * (pagination, filtering, sorting). Pass ?include_stats=1 to force inclusion.
     */
    public function getData(Request $request): array
    {
        $query = $this->buildListQuery($request);
        $paginator = $query->paginate($this->getPerPage($request))->onEachSide(1);

        // Include statistics unless explicitly excluded. Inertia v3 sends X-Requested-With
        // on every navigation, so ajax() is always true — the old check skipped stats on all
        // Inertia page visits. Services can still override alwaysIncludeStatistics() if needed.
        $includeStats = ! $request->boolean('exclude_stats');

        // Build PaginatedData-compatible payload with transformed items
        $rows = $paginator->toArray();
        $rows['data'] = $this->transformItems($paginator->items());

        $data = [
            'rows' => $rows,
            'filters' => $this->collectRequestFilters($request),
            'statistics' => $includeStats ? $this->getStatistics() : [],
        ];

        if ($this->scaffold()->shouldIncludeEmptyStateConfigInInertia()) {
            $data['empty_state_config'] = $this->getEmptyStateConfig();
        }

        return $data;
    }

    /**
     * Get scaffold runtime configuration with service-level dynamic options applied.
     *
     * @return array<string, mixed>
     */
    public function getInertiaConfig(): array
    {
        $config = $this->scaffold()->toInertiaConfig();
        $currentStatus = $this->resolveInertiaConfigStatus(request());

        $config['columns'] = $this->getColumnsConfig();
        $config['filters'] = $this->getFiltersConfig();

        if ($this->scaffold()->shouldIncludeActionConfigInInertia()) {
            $config['actions'] = $this->getActionsConfig($currentStatus);
        }

        return $config;
    }

    /**
     * Collect current filter, sort, and pagination values from the request.
     *
     * Builds a typed filters object for the React Datagrid component,
     * combining standard parameters (search, status, sort, direction, per_page, view)
     * with any additional filter values defined in the scaffold definition.
     *
     * @return array<string, mixed>
     */
    public function collectRequestFilters(Request $request): array
    {
        $filters = [
            'search' => $request->input('search', ''),
            'status' => $request->input('status') ?? $request->route('status') ?? 'all',
            'sort' => $request->input('sort', $this->scaffold()->getDefaultSort() ?? 'created_at'),
            'direction' => $request->input('direction', $this->scaffold()->getDefaultSortDirection()),
            'per_page' => (int) $request->input('per_page', $this->scaffold()->getPerPage()),
            'view' => $request->input('view'),
        ];

        // Collect current values for each defined filter
        foreach ($this->scaffold()->filters() as $filter) {
            if (! array_key_exists($filter->key, $filters)) {
                if ($filter->type === 'date_range') {
                    $from = $request->input($filter->key.'_from');
                    $to = $request->input($filter->key.'_to');

                    $filters[$filter->key] = collect([$from, $to])
                        ->filter(fn ($value): bool => $value !== null && $value !== '')
                        ->implode(',');

                    continue;
                }

                $filters[$filter->key] = $request->input($filter->key, '');
            }
        }

        return $filters;
    }

    /**
     * Get statistics for status tabs
     *
     * Uses customizeStatisticsQuery() for scoping (e.g., multi-tenant filtering).
     * Override customizeStatisticsQuery() in your service to scope counts.
     */
    public function getStatistics(): array
    {
        $modelClass = $this->getModelClass();
        $statusField = $this->scaffold()->getStatusField();

        // Check if model uses SoftDeletes trait
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass));

        // Initialize stats with total count (excluding trash if supported)
        $totalQuery = $modelClass::query();
        $this->customizeStatisticsQuery($totalQuery);
        if ($usesSoftDeletes) {
            $totalQuery->withoutTrashed();
        }

        $stats = [
            'total' => $totalQuery->count(),
        ];

        // Add status-specific counts if status field exists
        if ($statusField) {
            $model = new $modelClass;
            $table = $model->getTable();

            if ($this->tableHasColumn($table, $statusField)) {
                $statusQuery = $modelClass::query()
                    ->selectRaw($statusField.', count(*) as count')
                    ->groupBy($statusField);

                $this->customizeStatisticsQuery($statusQuery);

                if ($usesSoftDeletes) {
                    $statusQuery->withoutTrashed();
                }

                $statusCounts = $statusQuery->pluck('count', $statusField)->toArray();
                $stats = array_merge($stats, $statusCounts);
            }
        }

        // Add trash count if model uses soft deletes
        if ($usesSoftDeletes) {
            $trashQuery = $modelClass::onlyTrashed();
            $this->customizeStatisticsQuery($trashQuery);
            $stats['trash'] = $trashQuery->count();
        }

        return $stats;
    }

    /**
     * Whether to always include statistics in getData() responses, even on AJAX requests.
     * Override in service classes that require tab counts on every response (e.g. CMS).
     */
    protected function alwaysIncludeStatistics(): bool
    {
        return false;
    }

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * Create a new model instance
     *
     * Note: Audit fields (created_by, updated_by) are set automatically
     * by AuditableTrait in model boot events. No need to set them here.
     */
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $modelClass = $this->getModelClass();

            $preparedData = $this->prepareCreateData($data);

            $model = $modelClass::create($preparedData);

            // Post-create hook
            $this->afterCreate($model, $data);

            return $model;
        });
    }

    /**
     * Update an existing model instance
     *
     * Note: updated_by is set automatically by AuditableTrait.
     */
    public function update(Model $model, array $data): Model
    {
        return DB::transaction(function () use ($model, $data) {
            $preparedData = $this->prepareUpdateData($data);

            $model->update($preparedData);

            // Post-update hook
            $this->afterUpdate($model, $data);

            return $model->fresh();
        });
    }

    /**
     * Delete (soft delete) a model instance
     *
     * Note: deleted_by is set automatically by AuditableTrait's deleting event.
     */
    public function delete(Model $model): void
    {
        DB::transaction(function () use ($model): void {
            // Pre-delete hook
            $this->beforeDelete($model);

            $model->delete();

            // Post-delete hook
            $this->afterDelete($model);
        });
    }

    /**
     * Restore a soft-deleted model instance
     *
     * Note: updated_by is set automatically by AuditableTrait.
     * We only need to clear deleted_by explicitly.
     */
    public function restore(int|string $id): Model
    {
        return DB::transaction(function () use ($id) {
            $modelClass = $this->getModelClass();
            $model = $modelClass::withTrashed()->findOrFail($id);

            // Pre-restore hook (B4 fix)
            $this->beforeRestore($model);

            // Set deleted_by dirty and let restore() save in one query (B9 fix)
            $model->deleted_by = null;
            $model->restore();

            // Post-restore hook
            $this->afterRestore($model);

            return $model->fresh();
        });
    }

    /**
     * Permanently delete a model instance.
     * Only items that are already trashed can be permanently deleted.
     * Accepts a Model instance directly to avoid redundant lookups (I7 fix).
     */
    public function forceDelete(int|string|Model $modelOrId): void
    {
        DB::transaction(function () use ($modelOrId): void {
            if ($modelOrId instanceof Model) {
                $model = $modelOrId;
            } else {
                $modelClass = $this->getModelClass();
                $model = $modelClass::withTrashed()->findOrFail($modelOrId);
            }

            // Ensure the item is trashed before allowing permanent deletion
            throw_unless($model->trashed(), RuntimeException::class, 'Cannot permanently delete an item that is not in trash. Please move to trash first.');

            // Pre-force-delete hook
            $this->beforeForceDelete($model);

            $model->forceDelete();

            // Post-force-delete hook
            $this->afterForceDelete($model);
        });
    }

    /**
     * Find a model by ID (or route key), optionally including trashed.
     */
    public function findModel(int|string $id, bool $withTrashed = false): Model
    {
        $modelClass = $this->getModelClass();
        $model = new $modelClass;
        $routeKeyName = $model->getRouteKeyName();

        $query = $modelClass::query();

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass));

        if ($withTrashed && $usesSoftDeletes) {
            $query->withTrashed();
        }

        return $query->where($routeKeyName, $id)->firstOrFail();
    }

    // =========================================================================
    // BULK ACTIONS
    // =========================================================================

    /**
     * Handle bulk action (DataGrid format)
     *
     * Enforces per-action authorization:
     * - delete: requires delete_{entity} permission
     * - restore: requires restore_{entity} permission
     * - force_delete: requires delete_{entity} permission
     * - custom actions: checked via authorizeBulkAction() hook
     */
    public function handleBulkAction(Request $request): array
    {
        $action = $request->input('action');
        $ids = $request->input('ids', []);
        $selectAll = $request->boolean('select_all');

        throw_if(empty($action), InvalidArgumentException::class, 'Action is required');

        // Authorize the bulk action based on action type
        $this->authorizeBulkAction($action);

        // Execute action
        if ($selectAll) {
            $query = $this->stripEagerLoads($this->buildListQuery($request));
            $result = $this->executeBulkActionOnQuery($action, $query, $request);
        } else {
            $ids = array_values(array_filter((array) $ids, static fn ($id): bool => $id !== null && $id !== ''));
            throw_if($ids === [], InvalidArgumentException::class, 'No items selected');

            $result = $this->executeBulkAction($action, $ids, $request);
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? 'Action completed successfully',
            'affected' => $result['affected'] ?? 0,
        ];
    }

    // =========================================================================
    // DataGrid CONFIG
    // =========================================================================

    /**
     * Get columns configuration for DataGrid
     */
    protected function getColumnsConfig(): array
    {
        return collect($this->scaffold()->columns())
            ->filter(fn ($col) => $col->visible)
            ->map(fn ($col) => $col->toArray())
            ->values()
            ->toArray();
    }

    /**
     * Get filters configuration for DataGrid
     */
    protected function getFiltersConfig(): array
    {
        return collect($this->scaffold()->filters())
            ->map(fn ($filter) => $filter->toArray())
            ->toArray();
    }

    /**
     * Normalize a list of value/label options into the datagrid filter map format.
     *
     * @param  array<int|string, mixed>  $options
     * @return array<string, string>
     */
    protected function normalizeFilterOptionMap(array $options): array
    {
        if (! array_is_list($options)) {
            return collect($options)
                ->mapWithKeys(fn ($label, $value): array => [(string) $value => (string) $label])
                ->all();
        }

        return collect($options)
            ->filter(fn ($option): bool => is_array($option) && array_key_exists('value', $option))
            ->mapWithKeys(fn (array $option): array => [
                (string) $option['value'] => (string) ($option['label'] ?? $option['value']),
            ])
            ->all();
    }

    /**
     * Get unified actions configuration for DataGrid
     * Filters by authorization and status conditions
     * Each action includes 'scope' (row, bulk, both) for frontend routing
     */
    protected function getActionsConfig(string $status = 'all'): array
    {
        return collect($this->scaffold()->actions())
            ->filter(fn ($action) => $action->authorized())
            ->filter(fn ($action) => $action->shouldShow($status))
            ->map(fn ($action) => $action->toArray())
            ->values()
            ->toArray();
    }

    protected function resolveInertiaConfigStatus(?Request $request): string
    {
        if (! $request instanceof Request) {
            return 'all';
        }

        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        return is_string($status) && trim($status) !== '' ? trim($status) : 'all';
    }

    /**
     * Hook for subclass statistics query customization.
     *
     * Override this in your service to scope statistics queries,
     * e.g., for multi-tenant filtering. By default, applies the same
     * scoping as customizeListQuery() with a null request.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    protected function customizeStatisticsQuery($query): void
    {
        // Override in subclass if statistics need different scoping than list queries.
        // Example: $query->where('agency_id', auth()->user()->agency_id);
    }

    /**
     * Cached Schema::hasColumn check.
     */
    protected function tableHasColumn(string $table, string $column): bool
    {
        $key = $table.':'.$column;

        if (array_key_exists($key, self::$schemaHasColumnCache)) {
            return self::$schemaHasColumnCache[$key];
        }

        return self::$schemaHasColumnCache[$key] = Schema::hasColumn($table, $column);
    }

    /**
     * Remove eager loads from a query builder (bulk actions shouldn't hydrate relations).
     */
    protected function stripEagerLoads(Builder $query): Builder
    {
        $query->withoutEagerLoads();
        $query->setEagerLoads([]);

        return $query;
    }

    /**
     * Get empty state configuration
     */
    protected function getEmptyStateConfig(): array
    {
        $config = [
            'icon' => 'ri-inbox-line',
            'title' => sprintf('No %s Found', $this->getEntityPlural()),
            'message' => sprintf('There are no %s to display.', $this->getEntityPlural()),
        ];

        $createRoute = $this->scaffold()->getCreateRoute();
        if ($createRoute && Route::has($createRoute) && $this->canRenderEmptyStateCreateAction()) {
            $config['action'] = [
                'label' => 'Create '.$this->getEntityName(),
                'url' => route($createRoute),
            ];
        }

        return $config;
    }

    protected function canRenderEmptyStateCreateAction(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if ($this->scaffold()->requiresSuperUserAccess()) {
            return true;
        }

        $permissionPrefix = $this->scaffold()->getPermissionPrefix();

        if ($permissionPrefix === '') {
            return false;
        }

        return Auth::user()?->can('add_'.$permissionPrefix) ?? false;
    }

    // =========================================================================
    // QUERY BUILDING
    // =========================================================================

    /**
     * Build the list query with filters, search, and sorting
     */
    protected function buildListQuery(Request $request): Builder
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query();

        // Handle trash status specially
        // ⚠️ Check BOTH query param (?status=trash) AND route parameter (/entities/trash)
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Merge route status into request for downstream methods (filters, etc.)
        if (! $request->has('status') && $request->route('status')) {
            $request->merge(['status' => $status]);
        }

        if ($status === 'trash') {
            $query->onlyTrashed();
        }

        // Apply eager loading
        $this->applyEagerLoading($query);

        // Apply search
        $this->applySearch($query, $request);

        // Apply status filter (skip if viewing trash)
        $hasMultiStatusFilter = $request->has('statuses')
            && $request->input('statuses') !== null
            && $request->input('statuses') !== ''
            && $request->input('statuses') !== [];

        if ($status !== 'trash' && ! $hasMultiStatusFilter) {
            $this->applyStatusFilter($query, $request);
        }

        // Apply additional filters
        $this->applyFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        // Allow subclass customization
        $this->customizeListQuery($query, $request);

        return $query;
    }

    /**
     * Apply eager loading for relationships
     */
    protected function applyEagerLoading(Builder $query): void
    {
        $relationships = $this->getListEagerLoadRelationships();
        if (! empty($relationships)) {
            $query->with($relationships);
        }
    }

    /**
     * Get relationships to eager load for index/list queries.
     *
     * Override this when list pages need a different relationship set than other
     * resource operations. By default it preserves the legacy hook.
     */
    protected function getListEagerLoadRelationships(): array
    {
        return $this->getEagerLoadRelationships();
    }

    /**
     * Get relationships to eager load
     * Override in subclass to add relationships
     */
    protected function getEagerLoadRelationships(): array
    {
        $relationships = [];
        $modelClass = $this->getModelClass();
        $model = new $modelClass;
        $fillable = $model->getFillable();

        // Auto-detect audit relationships
        if (in_array('created_by', $fillable) && method_exists($model, 'createdBy')) {
            $relationships[] = 'createdBy:id,first_name,last_name';
        }

        if (in_array('updated_by', $fillable) && method_exists($model, 'updatedBy')) {
            $relationships[] = 'updatedBy:id,first_name,last_name';
        }

        // Auto-detect relationship columns (e.g. 'user.name', 'category.title')
        // This prevents N+1 queries when displaying relationship data in DataGrid
        $relationColumns = collect($this->scaffold()->columns())
            ->filter(fn ($col): bool => str_contains((string) $col->key, '.'))
            ->map(fn ($col) => str($col->key)->beforeLast('.')->toString())
            ->unique()
            ->filter(fn ($relation): bool => method_exists($model, $relation))
            ->values()
            ->toArray();

        return array_unique(array_merge($relationships, $relationColumns));
    }

    /**
     * Apply search filter
     */
    protected function applySearch(Builder $query, Request $request): void
    {
        $search = $request->input('search');

        if (empty($search)) {
            return;
        }

        $searchableColumns = $this->scaffold()->getSearchableColumns();

        if (empty($searchableColumns)) {
            return;
        }

        $fulltextColumns = $this->scaffold()->getFulltextColumns();

        // Escape LIKE wildcard characters to prevent unintended pattern matching
        $escapedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);

        $query->where(function (Builder $q) use ($search, $escapedSearch, $searchableColumns, $fulltextColumns): void {
            foreach ($searchableColumns as $column) {
                // Use full-text search for columns with a GIN/fulltext index
                if (in_array($column, $fulltextColumns)) {
                    $q->orWhereFullText($column, $search);

                    continue;
                }

                // Handle relationship columns (e.g. 'user.name')
                if (str_contains($column, '.')) {
                    $relationName = (string) str($column)->beforeLast('.');
                    $relationColumn = (string) str($column)->afterLast('.');

                    $q->orWhereHas($relationName, function ($sq) use ($relationColumn, $escapedSearch): void {
                        $sq->where($relationColumn, 'ilike', sprintf('%%%s%%', $escapedSearch));
                    });
                } else {
                    $q->orWhere($column, 'ilike', sprintf('%%%s%%', $escapedSearch));
                }
            }
        });
    }

    /**
     * Apply status filter
     */
    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $statusField = $this->scaffold()->getStatusField();

        if (! $statusField) {
            return;
        }

        $status = $request->input('status');

        // Skip 'all' or empty status
        if (empty($status) || $status === 'all') {
            return;
        }

        $query->where($statusField, $status);
    }

    /**
     * Apply additional filters from request
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        foreach ($this->scaffold()->filters() as $filter) {
            $value = $request->input($filter->key);

            // Normalize multi-select values.
            // Some frontends submit multiple selections as a comma-separated string.
            if ($filter->type === 'select' && $filter->multiple && is_string($value) && str_contains($value, ',')) {
                $value = array_values(array_filter(array_map(trim(...), explode(',', $value)), fn ($v): bool => $v !== ''));
            }

            // Allow filters to delegate to a custom query-builder method.
            // Useful for relationship-based filters where the filter key is not a real column.
            $applyMethod = $filter->meta['apply'] ?? null;
            if (
                is_string($applyMethod)
                && $applyMethod !== ''
                && $value !== null
                && $value !== ''
                && method_exists($query, $applyMethod)
            ) {
                $query->{$applyMethod}($value);

                continue;
            }

            switch ($filter->type) {
                case 'select':
                    if ($value === null || $value === '') {
                        break;
                    }

                    if ($filter->multiple && is_array($value)) {
                        $query->whereIn($filter->key, $value);
                    } else {
                        $query->where($filter->key, $value);
                    }

                    break;

                case 'date_range':
                    // Date range uses ${key}_from and ${key}_to, not $value
                    $from = $request->input($filter->key.'_from');
                    $to = $request->input($filter->key.'_to');

                    if ($from) {
                        $query->whereDate($filter->key, '>=', $from);
                    }

                    if ($to) {
                        $query->whereDate($filter->key, '<=', $to);
                    }

                    break;

                case 'date':
                    if ($value === null || $value === '') {
                        break;
                    }

                    $query->whereDate($filter->key, $value);
                    break;

                case 'boolean':
                    if ($value === null || $value === '') {
                        break;
                    }

                    $parsed = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    if ($parsed === null) {
                        break;
                    }

                    $query->where($filter->key, $parsed);
                    break;

                case 'search':
                    if ($value === null || $value === '') {
                        break;
                    }

                    $escapedValue = str_replace(['%', '_'], ['\%', '\_'], (string) $value);
                    $query->where($filter->key, 'ilike', sprintf('%%%s%%', $escapedValue));
                    break;
                case 'number':
                    if ($value === null || $value === '') {
                        break;
                    }

                    $query->where($filter->key, $value);
                    break;
            }
        }
    }

    /**
     * Apply sorting (DataGrid uses sort_column and sort_direction)
     */
    protected function applySorting(Builder $query, Request $request): void
    {
        $sortBy = $request->input(
            'sort_column',
            $request->input('sort', $this->scaffold()->getDefaultSort()),
        );
        $sortOrder = $request->input(
            'sort_direction',
            $request->input(
                'direction',
                $this->scaffold()->getDefaultSortDirection(),
            ),
        );

        if (! $sortBy) {
            return;
        }

        // Validate sort column is allowed
        $sortableColumns = $this->scaffold()->getSortableColumns();

        // Always allow sorting by created_at
        if (! in_array($sortBy, $sortableColumns) && $sortBy !== 'created_at') {
            $sortBy = $this->scaffold()->getDefaultSort();
        }

        // Validate direction
        $sortOrder = strtolower((string) $sortOrder) === 'asc' ? 'asc' : 'desc';

        if ($sortBy) {
            // Get the actual DB column to sort on (handles computed/virtual columns)
            $actualSortColumn = $this->scaffold()->getActualSortColumn($sortBy) ?? $sortBy;
            $query->orderBy($actualSortColumn, $sortOrder);

            $modelClass = $this->getModelClass();
            $model = new $modelClass;
            $qualifiedKeyName = $model->qualifyColumn($model->getKeyName());

            if (
                $actualSortColumn !== $model->getKeyName()
                && $actualSortColumn !== $qualifiedKeyName
            ) {
                $query->orderBy($qualifiedKeyName, $sortOrder);
            }
        }
    }

    /**
     * Hook for subclass query customization
     */
    protected function customizeListQuery(Builder $query, Request $request): void
    {
        // Override in subclass
    }

    // =========================================================================
    // RESPONSE TRANSFORMATION
    // =========================================================================

    /**
     * Transform items for response using resource class
     */
    protected function transformItems(array $items): array
    {
        $resourceClass = $this->getResourceClass();

        if ($resourceClass && class_exists($resourceClass)) {
            return $resourceClass::collection(collect($items))->resolve();
        }

        // Default transformation - add computed fields
        return collect($items)->map(fn ($item) => $this->transformItem($item))->toArray();
    }

    /**
     * Transform a single item (default implementation)
     */
    protected function transformItem(Model $item): array
    {
        $data = $item->toArray();

        // Add ID for row identification
        $data['id'] = $item->getKey();

        // Add formatted dates
        $createdAt = $item->getAttribute('created_at');
        if ($createdAt instanceof CarbonInterface) {
            $data['created_at_formatted'] = $createdAt->format('M d, Y');
            $data['created_at_human'] = $createdAt->diffForHumans();
        }

        $updatedAt = $item->getAttribute('updated_at');
        if ($updatedAt instanceof DateTimeInterface) {
            $data['updated_at_formatted'] = $updatedAt->format('M d, Y');
        }

        // Add status label/badge if status field exists
        $statusField = $this->scaffold()->getStatusField();
        if ($statusField && isset($item->{$statusField})) {
            $status = $item->{$statusField};
            if (is_object($status) && method_exists($status, 'label')) {
                $data['status_label'] = $status->label();
            }

            if (is_object($status) && method_exists($status, 'badge')) {
                $data['status_badge'] = $status->badge();
            }
        }

        // Add row actions
        $data['actions'] = $this->getRowActions($item);

        return $data;
    }

    /**
     * Get row actions for an item
     *
     * Delegates to Action::resolveForRow() — the single source of truth
     * for action filtering, authorization, status conditions, and URL building.
     * Override in subclass for custom route parameter logic (e.g., nested routes).
     */
    protected function getRowActions(Model $item): array
    {
        return Action::resolveForRow($this->scaffold(), $item);
    }

    /**
     * Resolve route parameter name for a model
     * Converts entity name to camelCase for route parameter (e.g., 'Email Provider' -> 'emailProvider')
     */
    protected function resolveRouteParam(Model $item): string
    {
        $routeKeyName = $item->getRouteKeyName();

        if ($routeKeyName !== 'id') {
            return $routeKeyName;
        }

        // Model uses default 'id', but routes might use entity name (e.g., {ticket}, {emailProvider})
        // Convert entity name to camelCase for route parameter
        $entityName = $this->scaffold()->getEntityName();

        return str($entityName)->camel()->toString();
    }

    /**
     * Check if user can perform action
     */
    protected function canPerformAction(?string $permission): bool
    {
        if (! $permission) {
            return true;
        }

        return Auth::check() && Auth::user()->can($permission);
    }

    /**
     * Convert key to title case
     */
    protected function titleCase(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * Get resource class for transformation
     *
     * Override in subclass, or define in the ScaffoldDefinition via getResourceClass().
     */
    protected function getResourceClass(): ?string
    {
        return $this->scaffold()->getResourceClass();
    }

    /**
     * Build pagination data (DataGrid format)
     */
    protected function buildPaginationData(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ];
    }

    /**
     * Get items per page
     */
    protected function getPerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', $this->scaffold()->getPerPage());

        return min(max($perPage, 5), 100);
    }

    // =========================================================================
    // CRUD HOOKS (Override in subclass)
    // =========================================================================

    /**
     * Prepare data for creation
     */
    protected function prepareCreateData(array $data): array
    {
        return $data;
    }

    /**
     * Prepare data for update
     */
    protected function prepareUpdateData(array $data): array
    {
        return $data;
    }

    /**
     * Hook called after model creation
     */
    protected function afterCreate(Model $model, array $data): void
    {
        // Override in subclass
    }

    /**
     * Hook called after model update
     */
    protected function afterUpdate(Model $model, array $data): void
    {
        // Override in subclass
    }

    /**
     * Hook called before model deletion
     */
    protected function beforeDelete(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook called after model deletion
     */
    protected function afterDelete(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook called before model restoration
     */
    protected function beforeRestore(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook called after model restoration
     */
    protected function afterRestore(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook called before permanent deletion
     */
    protected function beforeForceDelete(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook called after permanent deletion
     */
    protected function afterForceDelete(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Authorize a bulk action based on action type
     *
     * Standard actions map to permissions:
     * - delete: delete_{entity}
     * - restore: restore_{entity}
     * - force_delete: delete_{entity}
     *
     * Custom actions can be authorized by overriding authorizeCustomBulkAction()
     *
     * @throws AuthorizationException
     */
    protected function authorizeBulkAction(string $action): void
    {
        $permissionPrefix = $this->scaffold()->getPermissionPrefix();

        $requiredPermission = match ($action) {
            'delete', 'force_delete' => 'delete_'.$permissionPrefix,
            'restore' => 'restore_'.$permissionPrefix,
            default => null,
        };

        if ($requiredPermission !== null) {
            throw_if(! Auth::check() || ! Auth::user()->can($requiredPermission), AuthorizationException::class, sprintf("You do not have permission to perform the '%s' action.", $action));

            return;
        }

        // For custom actions, delegate to hook method
        $this->authorizeCustomBulkAction($action);
    }

    /**
     * Authorize a custom bulk action (override in subclass)
     *
     * Called for any bulk action that is not delete/restore/force_delete.
     * Override this method to implement authorization for custom bulk actions.
     *
     * @throws AuthorizationException
     */
    protected function authorizeCustomBulkAction(string $action): void
    {
        // By default, custom bulk actions require authentication
        // Subclasses should override to add specific permission checks
        throw_unless(Auth::check(), AuthorizationException::class, 'You must be authenticated to perform this action.');
    }

    /**
     * Execute a specific bulk action
     */
    protected function executeBulkAction(string $action, array $ids, Request $request): array
    {
        // Scope ID-based bulk actions to the same constraints as the listing query.
        // This prevents acting on records outside the current user's allowed scope.
        $query = $this->stripEagerLoads($this->buildListQuery($request))->whereKey($ids);

        return $this->executeBulkActionOnQuery($action, $query, $request);
    }

    /**
     * Execute bulk action against a query (scales for select-all)
     *
     * Processes in chunks to avoid loading all IDs into memory.
     * Uses model instance operations for standard actions so observers/events can fire.
     */
    protected function executeBulkActionOnQuery(string $action, $query, Request $request): array
    {
        $modelClass = $this->getModelClass();
        $auditUserId = $this->resolveAuditUserId();
        $model = new $modelClass;
        $keyName = $model->getKeyName();

        $table = $model->getTable();
        $hasDeletedBy = $this->tableHasColumn($table, 'deleted_by');
        $hasUpdatedBy = $this->tableHasColumn($table, 'updated_by');

        $affected = 0;

        // Ensure soft-delete helpers are available when needed
        if (in_array($action, ['restore', 'force_delete'], true) && method_exists($query, 'withTrashed')) {
            $query = $query->withTrashed();
        }

        if (in_array($action, ['restore', 'force_delete'], true) && method_exists($query, 'onlyTrashed')) {
            $query = $query->onlyTrashed();
        }

        switch ($action) {
            case 'delete':
            case 'restore':
            case 'force_delete':
                $query->chunkById(200, function ($models) use ($action, $auditUserId, $hasDeletedBy, $hasUpdatedBy, &$affected): void {
                    foreach ($models as $item) {
                        if ($action === 'delete') {
                            if ($auditUserId && $hasDeletedBy) {
                                $item->deleted_by = $auditUserId;
                                if (method_exists($item, 'saveQuietly')) {
                                    $item->saveQuietly();
                                } else {
                                    $item->save();
                                }
                            }

                            $this->beforeDelete($item);
                            $item->delete();
                            $this->afterDelete($item);
                            $affected++;

                            continue;
                        }

                        if ($action === 'restore') {
                            if ($hasDeletedBy) {
                                $item->deleted_by = null;
                            }

                            if ($auditUserId && $hasUpdatedBy) {
                                $item->updated_by = $auditUserId;
                            }

                            $this->beforeRestore($item);
                            $item->restore();
                            $this->afterRestore($item);
                            $affected++;

                            continue;
                        }

                        // force_delete
                        $this->beforeForceDelete($item);
                        $item->forceDelete();
                        $this->afterForceDelete($item);
                        $affected++;
                    }
                }, $keyName);

                return match ($action) {
                    'delete' => ['message' => $affected.' item(s) moved to trash', 'affected' => $affected],
                    'restore' => ['message' => $affected.' item(s) restored', 'affected' => $affected],
                    'force_delete' => ['message' => $affected.' item(s) permanently deleted', 'affected' => $affected],
                };

            default:
                return $this->handleCustomBulkActionForQuery($action, $query, $request);
        }
    }

    /**
     * Default implementation for select-all custom actions.
     *
     * Chunks IDs from the query and delegates to handleCustomBulkAction().
     */
    protected function handleCustomBulkActionForQuery(string $action, $query, Request $request): array
    {
        $modelClass = $this->getModelClass();
        $model = new $modelClass;
        $keyName = $model->getKeyName();

        $affected = 0;
        $lastMessage = null;

        $query->select([$keyName])->chunkById(500, function ($rows) use ($action, $request, $keyName, &$affected, &$lastMessage): void {
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = $row->{$keyName};
            }

            if ($ids === []) {
                return;
            }

            $result = $this->handleCustomBulkAction($action, $ids, $request);
            $lastMessage = $result['message'];
            $affected += (int) $result['affected'];
        }, $keyName);

        return [
            'message' => $lastMessage ?? $affected.' item(s) affected',
            'affected' => $affected,
        ];
    }

    /**
     * Handle custom bulk action (override in subclass)
     *
     * @return array{message: string, affected: int}
     */
    protected function handleCustomBulkAction(string $action, array $ids, ?Request $request = null): array
    {
        throw new InvalidArgumentException('Unknown bulk action: '.$action);
    }

    // =========================================================================
    // AUDIT HELPERS
    // =========================================================================

    /**
     * Resolve authenticated user ID for audit fields
     */
    protected function resolveAuditUserId(): ?int
    {
        if (! Auth::check()) {
            return null;
        }

        return Auth::id();
    }
}
