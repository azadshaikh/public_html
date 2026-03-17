<?php

declare(strict_types=1);

namespace App\Scaffold;

use App\Http\Middleware\EnsureSuperUserAccess;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use ReflectionClass;

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
     * Optional override for the Inertia page prefix when it intentionally differs
     * from the route-derived convention.
     */
    protected ?string $inertiaPagePrefix = null;

    /**
     * Whether this scaffold is restricted to super users only.
     */
    protected bool $requiresSuperUserAccess = false;

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
     * Determine whether this scaffold is restricted to super users.
     */
    public function requiresSuperUserAccess(): bool
    {
        return $this->requiresSuperUserAccess;
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
     * Determine whether the scaffold model supports soft deletes.
     */
    public function usesSoftDeletes(): bool
    {
        $modelClass = $this->getModelClass();

        if (! class_exists($modelClass)) {
            return false;
        }

        return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    }

    /**
     * Determine whether the scaffold should enforce conventional route names.
     */
    public function shouldValidateConventionalRouteNames(): bool
    {
        return true;
    }

    /**
     * Derive the Inertia page prefix from the route prefix.
     */
    public function getInertiaPagePrefix(): string
    {
        if (is_string($this->inertiaPagePrefix) && $this->inertiaPagePrefix !== '') {
            return $this->inertiaPagePrefix;
        }

        $segments = collect(explode('.', $this->getRoutePrefix()))
            ->filter(fn (string $segment): bool => $segment !== '');

        if ($segments->first() === 'app') {
            $segments = $segments->slice(1)->values();
        }

        return $segments->implode('/');
    }

    /**
     * @return array{index: string, create: string, edit: string, show: string}
     */
    public function expectedPageComponents(): array
    {
        $prefix = $this->getInertiaPagePrefix();

        return [
            'index' => $prefix.'/index',
            'create' => $prefix.'/create',
            'edit' => $prefix.'/edit',
            'show' => $prefix.'/show',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function expectedRouteNames(): array
    {
        $routePrefix = $this->getRoutePrefix();

        $routes = [
            'index' => $routePrefix.'.index',
            'create' => $routePrefix.'.create',
            'store' => $routePrefix.'.store',
            'show' => $routePrefix.'.show',
            'edit' => $routePrefix.'.edit',
            'update' => $routePrefix.'.update',
            'destroy' => $routePrefix.'.destroy',
            'bulk-action' => $routePrefix.'.bulk-action',
        ];

        if ($this->usesSoftDeletes()) {
            $routes['restore'] = $routePrefix.'.restore';
            $routes['force-delete'] = $routePrefix.'.force-delete';
        }

        return $routes;
    }

    /**
     * @return array<string, string>
     */
    public function expectedPermissionNames(): array
    {
        $prefix = $this->getPermissionPrefix();

        $permissions = [
            'view' => 'view_'.$prefix,
            'add' => 'add_'.$prefix,
            'edit' => 'edit_'.$prefix,
            'delete' => 'delete_'.$prefix,
        ];

        if ($this->usesSoftDeletes()) {
            $permissions['restore'] = 'restore_'.$prefix;
        }

        return $permissions;
    }

    /**
     * @return array<string, string>
     */
    public function expectedAbilityMap(): array
    {
        return collect($this->expectedPermissionNames())
            ->mapWithKeys(fn (string $permission): array => [Str::camel($permission) => $permission])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function expectedFilePaths(): array
    {
        $files = [
            'definition' => $this->resolveClassFilePath(static::class),
            'model' => $this->resolveClassFilePath($this->getModelClass()),
        ];

        $requestClass = $this->getRequestClass();

        if (is_string($requestClass) && $requestClass !== '') {
            $files['request'] = $this->resolveClassFilePath($requestClass);
        }

        $resourceClass = $this->getResourceClass();

        if (is_string($resourceClass) && $resourceClass !== '') {
            $files['resource'] = $this->resolveClassFilePath($resourceClass);
        }

        foreach ($this->expectedPageComponents() as $pageName => $component) {
            $files['page:'.$pageName] = $this->resolvePageFilePath($component);
        }

        $moduleName = $this->getOwningModuleName();

        if (is_string($moduleName) && $moduleName !== '') {
            $files['abilities'] = base_path(sprintf('modules/%s/config/abilities.php', $moduleName));
        }

        return collect($files)
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function expectedTestPaths(): array
    {
        $entityTestName = Str::studly((string) Str::of($this->getEntityName())->singular()->replace(['-', '_'], ' '));
        $moduleName = $this->getOwningModuleName();

        if (is_string($moduleName) && $moduleName !== '') {
            return [
                'crud' => base_path(sprintf('modules/%s/tests/Feature/%sCrudTest.php', $moduleName, $entityTestName)),
            ];
        }

        return [
            'crud' => base_path(sprintf('tests/Feature/%sCrudTest.php', $entityTestName)),
        ];
    }

    public function getOwningModuleName(): ?string
    {
        if (! str_starts_with(static::class, 'Modules\\')) {
            return null;
        }

        $segments = explode('\\', static::class);

        return $segments[1] ?? null;
    }

    protected function resolvePageFilePath(string $component): string
    {
        $moduleName = $this->getOwningModuleName();

        if (is_string($moduleName) && $moduleName !== '') {
            return base_path(sprintf('modules/%s/resources/js/pages/%s.tsx', $moduleName, $component));
        }

        return resource_path(sprintf('js/pages/%s.tsx', $component));
    }

    protected function resolveClassFilePath(string $class): ?string
    {
        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            $filePath = $reflection->getFileName();

            if (is_string($filePath) && $filePath !== '') {
                return $filePath;
            }
        }

        if (str_starts_with($class, 'App\\')) {
            return base_path('app/'.str_replace('\\', '/', substr($class, strlen('App\\'))).'.php');
        }

        if (str_starts_with($class, 'Modules\\')) {
            $segments = explode('\\', $class);
            $moduleName = $segments[1] ?? null;
            $relativePath = implode('/', array_slice($segments, 2));

            if (is_string($moduleName) && $moduleName !== '' && $relativePath !== '') {
                return base_path(sprintf('modules/%s/app/%s.php', $moduleName, $relativePath));
            }
        }

        return null;
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

    // =========================================================================
    // INERTIA CONFIG EXPORT
    // =========================================================================

    /**
     * Export the full scaffold configuration as Inertia-consumable props.
     *
     * Returns columns, filters, actions, status tabs, and settings
     * that the React DataGrid component can consume directly.
     *
     * @return array{
     *     columns: array<int, array<string, mixed>>,
     *     filters: array<int, array<string, mixed>>,
     *     actions: array<int, array<string, mixed>>,
     *     statusTabs: array<int, array<string, mixed>>,
     *     settings: array<string, mixed>,
     * }
     */
    public function toInertiaConfig(): array
    {
        return [
            'columns' => collect($this->columns())
                ->filter(fn (Column $col): bool => $col->visible)
                ->map(fn (Column $col): array => $col->toArray())
                ->values()
                ->all(),

            'filters' => collect($this->filters())
                ->map(fn (Filter $filter): array => $filter->toArray())
                ->values()
                ->all(),

            'actions' => collect($this->actions())
                ->filter(fn (Action $action): bool => $action->authorized())
                ->map(fn (Action $action): array => $action->toArray())
                ->values()
                ->all(),

            'statusTabs' => collect($this->statusTabs())
                ->map(fn (StatusTab $tab): array => $tab->toArray())
                ->values()
                ->all(),

            'settings' => [
                'perPage' => $this->getPerPage(),
                'defaultSort' => $this->getDefaultSort(),
                'defaultDirection' => $this->getDefaultSortDirection(),
                'enableBulkActions' => $this->hasBulkActions(),
                'enableExport' => $this->hasExport(),
                'hasNotes' => $this->hasNotes(),
                'entityName' => $this->getEntityName(),
                'entityPlural' => $this->getEntityPlural(),
                'routePrefix' => $this->getRoutePrefix(),
                'statusField' => $this->getStatusField(),
            ],
        ];
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
        if ($this->requiresSuperUserAccess()) {
            return [
                new Middleware(EnsureSuperUserAccess::class),
                new Middleware('throttle:30,1', only: ['bulkAction', 'forceDelete']),
            ];
        }

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
            $this->withOptionalPermission(
                Action::make('show')
                    ->label('View')
                    ->icon('ri-eye-line')
                    ->route($routePrefix.'.show')
                    ->forRow(),
                'view_'.$prefix,
            ),

            // Row-only: Edit
            $this->withOptionalPermission(
                Action::make('edit')
                    ->label('Edit')
                    ->icon('ri-pencil-line')
                    ->route($routePrefix.'.edit')
                    ->forRow(),
                'edit_'.$prefix,
            ),

            // Both row and bulk: Delete (move to trash)
            $this->withOptionalPermission(
                Action::make('delete')
                    ->label('Move to Trash')
                    ->icon('ri-delete-bin-line')
                    ->danger()
                    ->route($routePrefix.'.destroy')
                    ->method('DELETE')
                    ->confirm('Are you sure you want to move this item to trash?')
                    ->confirmBulk('Move {count} items to trash?')
                    ->hideOnStatus('trash')
                    ->forBoth(),
                'delete_'.$prefix,
            ),

            // Both row and bulk: Restore (only on trash)
            $this->withOptionalPermission(
                Action::make('restore')
                    ->label('Restore')
                    ->icon('ri-refresh-line')
                    ->success()
                    ->route($routePrefix.'.restore')
                    ->method('PATCH')
                    ->confirm('Are you sure you want to restore this item?')
                    ->confirmBulk('Restore {count} items?')
                    ->showOnStatus('trash')
                    ->forBoth(),
                'restore_'.$prefix,
            ),

            // Both row and bulk: Force delete (only on trash)
            $this->withOptionalPermission(
                Action::make('force_delete')
                    ->label('Delete Permanently')
                    ->icon('ri-delete-bin-fill')
                    ->danger()
                    ->route($routePrefix.'.force-delete')
                    ->method('DELETE')
                    ->confirm('⚠️ This cannot be undone!')
                    ->confirmBulk('⚠️ Permanently delete {count} items? This cannot be undone!')
                    ->showOnStatus('trash')
                    ->forBoth(),
                'delete_'.$prefix,
            ),
        ];
    }

    protected function withOptionalPermission(Action $action, string $permission): Action
    {
        if ($this->requiresSuperUserAccess()) {
            return $action;
        }

        return $action->permission($permission);
    }
}
