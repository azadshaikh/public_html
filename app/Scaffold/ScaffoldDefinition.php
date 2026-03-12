<?php

declare(strict_types=1);

namespace App\Scaffold;

use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Route;

/**
 * ScaffoldDefinition - Defines a complete CRUD scaffold configuration
 *
 * This abstract class should be extended for each entity that needs
 * a DataGrid/CRUD interface. It centralizes all configuration in one place.
 *
 * @example
 * class AddressDefinition extends ScaffoldDefinition
 * {
 *     protected string $entityName = 'Address';
 *     protected string $entityPlural = 'Addresses';
 *     protected string $routePrefix = 'addresses';
 *
 *     public function columns(): array
 *     {
 *         return [
 *             Column::make('name')->label('Name')->sortable()->searchable(),
 *             Column::make('city')->label('City')->sortable(),
 *             Column::make('status')->label('Status')->badge()->filterable(Status::class),
 *         ];
 *     }
 * }
 */
abstract class ScaffoldDefinition
{
    /**
     * Entity name (singular)
     */
    protected string $entityName = '';

    /**
     * Entity name (plural)
     */
    protected string $entityPlural = '';

    /**
     * Route prefix for this entity
     */
    protected string $routePrefix = '';

    /**
     * Permission prefix for authorization
     */
    protected string $permissionPrefix = '';

    /**
     * Default items per page
     */
    protected int $perPage = 10;

    /**
     * Default sort column
     */
    protected ?string $defaultSort = 'created_at';

    /**
     * Default sort direction
     */
    protected string $defaultSortDirection = 'desc';

    /**
     * Status field name (for status tabs)
     */
    protected ?string $statusField = 'status';

    /**
     * Enable bulk actions
     */
    protected bool $enableBulkActions = true;

    /**
     * Enable export
     */
    protected bool $enableExport = false;

    /**
     * Define table columns
     *
     * @return array<Column>
     */
    abstract public function columns(): array;

    /**
     * Define available filters
     *
     * @return array<Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Define all actions (row and bulk)
     *
     * Each action can specify its scope:
     * - forRow() - only in row dropdown
     * - forBulk() - only in bulk action bar
     * - forBoth() - in both places (default)
     *
     * Override this method to customize actions. Call defaultActions() to include standard CRUD actions.
     *
     * @return array<Action>
     */
    public function actions(): array
    {
        return $this->defaultActions();
    }

    /**
     * Define status tabs
     *
     * @return array<StatusTab>
     */
    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->default(),
        ];
    }

    /**
     * Get the FormRequest class for validation
     */
    public function getRequestClass(): ?string
    {
        return null;
    }

    /**
     * Get the Resource class for JSON transformation
     */
    public function getResourceClass(): ?string
    {
        return null;
    }

    /**
     * Get the model class for this scaffold
     */
    abstract public function getModelClass(): string;

    /**
     * Get searchable columns
     *
     * Returns actual DB columns to search on, handling computed columns
     * that specify alternate search columns.
     *
     * @return array<string>
     */
    public function getSearchableColumns(): array
    {
        $columns = [];

        foreach ($this->columns() as $col) {
            if (! $col->searchable) {
                continue;
            }

            // If searchColumns is specified, use those instead of the key
            if (! empty($col->searchColumns)) {
                foreach ($col->searchColumns as $searchCol) {
                    $columns[] = $searchCol;
                }
            } else {
                $columns[] = $col->key;
            }
        }

        return array_unique($columns);
    }

    /**
     * Get columns that have full-text indexes for search.
     *
     * @return array<string>
     */
    public function getFulltextColumns(): array
    {
        $columns = [];

        foreach ($this->columns() as $col) {
            if (! $col->searchable) {
                continue;
            }

            if (! $col->fulltext) {
                continue;
            }

            if (! empty($col->searchColumns)) {
                foreach ($col->searchColumns as $searchCol) {
                    $columns[] = $searchCol;
                }
            } else {
                $columns[] = $col->key;
            }
        }

        return array_unique($columns);
    }

    /**
     * Get sortable columns
     *
     * @return array<string>
     */
    public function getSortableColumns(): array
    {
        return collect($this->columns())
            ->filter(fn (Column $col): bool => $col->sortable)
            ->pluck('key')
            ->toArray();
    }

    /**
     * Get the actual DB column to sort on for a given column key
     *
     * Handles computed/virtual columns that specify alternate sort columns.
     *
     * @return string|null The actual column to sort on, or null if not sortable
     */
    public function getActualSortColumn(string $columnKey): ?string
    {
        foreach ($this->columns() as $col) {
            if ($col->key === $columnKey && $col->sortable) {
                // If sortColumn is specified, use that; otherwise use the key
                return $col->sortColumn ?? $col->key;
            }
        }

        return null;
    }

    /**
     * Get entity name (singular)
     * Auto-derives from class name if not set (e.g., AddressDefinition → Address)
     */
    public function getEntityName(): string
    {
        if ($this->entityName !== '' && $this->entityName !== '0') {
            return $this->entityName;
        }

        // Derive from class name: AddressDefinition → Address
        $className = class_basename(static::class);

        return str($className)->before('Definition')->toString();
    }

    /**
     * Get entity name (plural)
     * Auto-pluralizes from entityName if not set
     */
    public function getEntityPlural(): string
    {
        return $this->entityPlural ?: str($this->getEntityName())->plural()->toString();
    }

    /**
     * Get route prefix
     * Auto-derives from entityName if not set (e.g., Address → addresses)
     */
    public function getRoutePrefix(): string
    {
        if ($this->routePrefix !== '' && $this->routePrefix !== '0') {
            return $this->routePrefix;
        }

        // Derive from entity name: Address → addresses
        return str($this->getEntityName())->snake()->plural()->replace('_', '-')->toString();
    }

    /**
     * Get permission prefix
     *
     * If permissionPrefix is not explicitly set, derives from routePrefix:
     * - Extracts last segment: 'app.demo.movies' → 'movies'
     * - Normalizes to snake_case: 'email-providers' → 'email_providers'
     *
     * ⚠️ IMPORTANT: Permission names must NOT contain dots!
     * Examples:
     *   - routePrefix 'app.demo.movies' → permissionPrefix 'movies'
     *   - routePrefix 'admin.email-providers' → permissionPrefix 'email_providers'
     */
    public function getPermissionPrefix(): string
    {
        if ($this->permissionPrefix !== '' && $this->permissionPrefix !== '0') {
            return $this->permissionPrefix;
        }

        // Fallback: extract last segment of routePrefix and normalize
        // 'app.demo.movies' → 'movies'
        // 'email-providers' → 'email_providers'
        $routePrefix = $this->getRoutePrefix();
        $lastSegment = collect(explode('.', $routePrefix))->last();

        return str_replace('-', '_', $lastSegment);
    }

    /**
     * Get items per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get default sort
     */
    public function getDefaultSort(): ?string
    {
        return $this->defaultSort;
    }

    /**
     * Get default sort direction
     */
    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    /**
     * Get status field name
     */
    public function getStatusField(): ?string
    {
        return $this->statusField;
    }

    /**
     * Check if bulk actions are enabled
     */
    public function hasBulkActions(): bool
    {
        return $this->enableBulkActions;
    }

    /**
     * Check if export is enabled
     */
    public function hasExport(): bool
    {
        return $this->enableExport;
    }

    /**
     * Check if notes are enabled for this entity
     *
     * Override in subclass to enable notes panel on show pages.
     * The model must use the HasNotes trait.
     */
    public function hasNotes(): bool
    {
        return false;
    }

    /**
     * Get standard CRUD middleware configuration
     *
     * Returns middleware array for standard CRUD operations using the permissionPrefix.
     * Controllers can use this directly in their middleware() method.
     *
     * @return array<Middleware>
     *
     * @example
     * // In Controller:
     * public static function middleware(): array
     * {
     *     return (new MyDefinition())->getMiddleware();
     * }
     */
    public function getMiddleware(): array
    {
        $prefix = $this->getPermissionPrefix();

        return [
            new Middleware('permission:view_'.$prefix, only: ['index', 'show', 'data']),
            new Middleware('permission:add_'.$prefix, only: ['create', 'store']),
            new Middleware('permission:edit_'.$prefix, only: ['edit', 'update']),
            new Middleware('permission:delete_'.$prefix, only: ['destroy', 'bulkAction', 'forceDelete']),
            new Middleware('permission:restore_'.$prefix, only: ['restore']),
            // Rate-limit destructive operations: 30 requests per minute
            new Middleware('throttle:30,1', only: ['bulkAction', 'forceDelete']),
        ];
    }

    /**
     * Get index route name
     */
    public function getIndexRoute(): string
    {
        return $this->getRoutePrefix().'.index';
    }

    /**
     * Get create route name
     */
    public function getCreateRoute(): ?string
    {
        return $this->getRoutePrefix().'.create';
    }

    /**
     * Get edit route name
     */
    public function getEditRoute(): ?string
    {
        return $this->getRoutePrefix().'.edit';
    }

    /**
     * Get show route name
     */
    public function getShowRoute(): ?string
    {
        return $this->getRoutePrefix().'.show';
    }

    /**
     * Get bulk action route name
     */
    public function getBulkActionRoute(): ?string
    {
        return $this->getRoutePrefix().'.bulk-action';
    }

    /**
     * Get index view path (auto-discovers module views)
     */
    public function getIndexView(): string
    {
        return $this->resolveViewPath('index');
    }

    /**
     * Get create view path (auto-discovers module views)
     */
    public function getCreateView(): string
    {
        return $this->resolveViewPath('create');
    }

    /**
     * Get edit view path (auto-discovers module views)
     */
    public function getEditView(): string
    {
        return $this->resolveViewPath('edit');
    }

    /**
     * Get show view path (auto-discovers module views)
     */
    public function getShowView(): string
    {
        return $this->resolveViewPath('show');
    }

    /**
     * Export configuration for DataGrid JavaScript
     */
    public function toDataGridConfig(): array
    {
        // Determine current status context for conditional actions.
        // Supports both query-based (?status=trash) and path-based (/.../trash) status tabs.
        $currentStatus = request()->input('status')
            ?? request()->route('status')
            ?? request()->route('status_slug')
            ?? 'all';

        $routes = [
            'index' => route($this->getIndexRoute()),
        ];

        $createRoute = $this->getCreateRoute();
        if ($createRoute && Route::has($createRoute)) {
            $routes['create'] = route($createRoute);
        }

        $bulkActionRoute = $this->getBulkActionRoute();
        if ($this->hasBulkActions() && $bulkActionRoute && Route::has($bulkActionRoute)) {
            $routes['bulkAction'] = route($bulkActionRoute);
        }

        return [
            'entity' => [
                'name' => $this->getEntityName(),
                'plural' => $this->getEntityPlural(),
            ],
            'columns' => collect($this->columns())
                ->filter(fn (Column $col): bool => $col->visible)
                ->map(fn (Column $col): array => $col->toArray())
                ->values()
                ->all(),
            'filters' => collect($this->filters())
                ->map(fn (Filter $filter): array => $filter->toArray())
                ->all(),
            'actions' => collect($this->actions())
                ->filter(fn (Action $action): bool => $action->authorized() && $action->shouldShow((string) $currentStatus))
                ->map(fn (Action $action): array => $action->toArray())
                ->all(),
            'statusTabs' => collect($this->statusTabs())
                ->map(function (StatusTab $tab): array {
                    // Auto-generate URL if not set
                    if (! $tab->url) {
                        $tab->url = route($this->getIndexRoute(), $tab->key ?: null);
                    }

                    return $tab->toArray();
                })
                ->all(),
            'settings' => [
                'perPage' => $this->getPerPage(),
                'defaultSort' => $this->getDefaultSort(),
                'defaultSortDirection' => $this->getDefaultSortDirection(),
                'statusField' => $this->getStatusField(),
                'enableBulkActions' => $this->hasBulkActions(),
                'enableExport' => $this->hasExport(),
            ],
            'routes' => $routes,
        ];
    }

    /**
     * Export configuration as JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toDataGridConfig(), JSON_THROW_ON_ERROR);
    }

    /**
     * Get default CRUD actions with automatic permission integration
     *
     * Provides standard actions: show, edit, delete, restore, force_delete
     * All actions use the permissionPrefix for authorization.
     *
     * @return array<Action>
     */
    protected function defaultActions(): array
    {
        $prefix = $this->getPermissionPrefix();
        $routePrefix = $this->getRoutePrefix();

        return [
            // Row-only: View/Show
            Action::make('show')
                ->label('View')
                ->icon('ri-eye-line')
                ->route($routePrefix.'.show')
                ->permission('view_'.$prefix)
                ->forRow(),

            // Row-only: Edit
            Action::make('edit')
                ->label('Edit')
                ->icon('ri-pencil-line')
                ->route($routePrefix.'.edit')
                ->permission('edit_'.$prefix)
                ->forRow(),

            // Both row and bulk: Delete (move to trash)
            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->danger()
                ->route($routePrefix.'.destroy')
                ->method('DELETE')
                ->confirm('Are you sure you want to move this item to trash?')
                ->confirmBulk('Move {count} items to trash?')
                ->permission('delete_'.$prefix)
                ->hideOnStatus('trash')
                ->forBoth(),

            // Both row and bulk: Restore (only on trash)
            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->success()
                ->route($routePrefix.'.restore')
                ->method('PATCH')
                ->confirm('Are you sure you want to restore this item?')
                ->confirmBulk('Restore {count} items?')
                ->permission('restore_'.$prefix)
                ->showOnStatus('trash')
                ->forBoth(),

            // Both row and bulk: Force delete (only on trash)
            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-fill')
                ->danger()
                ->route($routePrefix.'.force-delete')
                ->method('DELETE')
                ->confirm('⚠️ This cannot be undone!')
                ->confirmBulk('⚠️ Permanently delete {count} items? This cannot be undone!')
                ->permission('delete_'.$prefix)
                ->showOnStatus('trash')
                ->forBoth(),
        ];
    }

    /**
     * Resolve view path with module auto-discovery
     *
     * Detects if the Definition is inside a module (Modules\ModuleName\...)
     * and returns the appropriate view path:
     * - Module: 'modulename::entityfolder.viewname' (e.g., 'demo::crud.index')
     * - App: 'app/path/viewname' (e.g., 'app/roles/index')
     *
     * Uses the LAST SEGMENT of routePrefix as the view folder.
     *
     * ⚠️ CAVEAT: Only works for flat view folder structures!
     * If your views are nested (e.g., 'demo::admin/users/index'), you must
     * override getIndexView(), getCreateView(), etc. explicitly.
     *
     * Examples:
     *   - routePrefix 'app.demo.crud' → view 'demo::crud.index' ✅
     *   - routePrefix 'app.demo.admin.users' → view 'demo::users.index' (only last segment!)
     *     For nested: override getIndexView() to return 'demo::admin.users.index'
     */
    protected function resolveViewPath(string $viewName): string
    {
        $definitionClass = static::class;
        $routePrefix = $this->getRoutePrefix();

        // Check if this Definition is in a module namespace
        if (str_starts_with($definitionClass, 'Modules\\')) {
            // Extract module name: Modules\Demo\Definitions\CrudDemoDefinition -> Demo
            $parts = explode('\\', $definitionClass);
            $moduleName = strtolower($parts[1]); // 'demo'

            // Use last segment of routePrefix as entity folder: app.demo.crud -> crud
            $routeParts = explode('.', $routePrefix);
            $entityFolder = end($routeParts); // 'crud'

            return sprintf('%s::%s.%s', $moduleName, $entityFolder, $viewName);
        }

        // App-level definition: use route prefix converted to path
        return str_replace('.', '/', $routePrefix).'/'.$viewName;
    }
}
