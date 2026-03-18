<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Enums\Status;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Scaffold\Column;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class RoleDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'app.roles';

    protected string $permissionPrefix = 'roles';

    protected ?string $statusField = 'status';

    protected bool $includeActionConfigInInertia = false;

    protected bool $includeEmptyStateConfigInInertia = false;

    protected bool $includeRowActionsInInertiaRows = false;

    public function getModelClass(): string
    {
        return Role::class;
    }

    public function getRequestClass(): ?string
    {
        return RoleRequest::class;
    }

    // ================================================================
    // DATAGRID COLUMNS
    // ================================================================

    public function columns(): array
    {
        return [
            // Bulk select
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            // Name
            Column::make('display_name')
                ->label('Role Name')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('250px'),

            // Permissions Count (Computed)
            Column::make('permissions_count')
                ->label('Permissions')
                ->sortable()
                ->center(),

            // Users Count (Computed)
            Column::make('users_count')
                ->label('Users')
                ->sortable()
                ->center(),

            // Status
            Column::make('status')
                ->label('Status')
                ->badgeVariants(Status::class)
                ->sortable(),

            // Created At
            Column::make('created_at')
                ->label('Created')
                ->datetime()
                ->sortable(),

            // Actions
            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    // ================================================================
    // DATAGRID FILTERS
    // ================================================================

    public function filters(): array
    {
        return [
            // Status filter handled by tabs
        ];
    }

    // ================================================================
    // STATUS TABS
    // ================================================================

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-list-check')
                ->color('primary')
                ->default(),

            StatusTab::make('active')
                ->label('Active')
                ->value('active')
                ->icon('ri-checkbox-circle-line')
                ->color('success'),

            StatusTab::make('inactive')
                ->label('Inactive')
                ->value('inactive')
                ->icon('ri-indeterminate-circle-line')
                ->color('secondary'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    // ================================================================
    // NOTES SUPPORT
    // ================================================================

    /**
     * Enable notes for roles.
     * The Role model must use the HasNotes trait.
     */
    public function hasNotes(): bool
    {
        return true;
    }
}
