<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Models\LoginAttempt;
use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class LoginAttemptDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    /**
     * Route prefix using dot notation
     */
    protected string $routePrefix = 'app.logs.login-attempts';

    /**
     * Permission prefix (used in middleware)
     */
    protected string $permissionPrefix = 'login_attempts';

    /**
     * Status field name
     */
    protected ?string $statusField = 'status';

    protected bool $includeActionConfigInInertia = false;

    protected bool $includeEmptyStateConfigInInertia = false;

    protected bool $enableBulkActions = true;

    /**
     * Return the Model class
     */
    public function getModelClass(): string
    {
        return LoginAttempt::class;
    }

    // ================================================================
    // DATAGRID COLUMNS
    // ================================================================

    public function columns(): array
    {
        return [
            // Bulk select checkbox
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            // Status badge
            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('100px'),

            // Email
            Column::make('email')
                ->label('Email')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('200px'),

            // IP Address
            Column::make('ip_address')
                ->label('IP Address')
                ->sortable()
                ->searchable()
                ->width('130px'),

            // Failure reason
            Column::make('failure_reason_label')
                ->label('Reason')
                ->width('150px'),

            // User
            Column::make('user_name')
                ->label('User')
                ->width('150px'),

            // Browser
            Column::make('browser')
                ->label('Browser')
                ->width('120px'),

            // Created date
            Column::make('created_at')
                ->label('Date')
                ->sortable()
                ->width('150px'),

            // Actions column
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
            // Date range filter
            Filter::dateRange('created_at')
                ->label('Date'),
        ];
    }

    // ================================================================
    // ROW & BULK ACTIONS
    // ================================================================

    public function actions(): array
    {
        return [
            // View action (row only)
            Action::make('show')
                ->label('View')
                ->icon('ri-eye-line')
                ->route($this->routePrefix.'.show')
                ->permission('view_'.$this->permissionPrefix)
                ->forRow(),

            // Delete action (row + bulk)
            Action::make('delete')
                ->label('Delete')
                ->icon('ri-delete-bin-line')
                ->route($this->routePrefix.'.destroy')
                ->method('DELETE')
                ->permission('delete_'.$this->permissionPrefix)
                ->danger()
                ->confirm('Are you sure you want to delete this login attempt?')
                ->confirmBulk('Delete {count} login attempt(s)?')
                ->hideOnStatus('trash')
                ->forBoth(),

            // Restore action (row + bulk, trash only)
            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->route($this->routePrefix.'.restore')
                ->method('PATCH')
                ->success()
                ->confirm('Restore this login attempt?')
                ->confirmBulk('Restore {count} login attempt(s)?')
                ->permission('delete_'.$this->permissionPrefix)
                ->showOnStatus('trash')
                ->forBoth(),

            // Force delete action (row + bulk, trash only)
            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-fill')
                ->route($this->routePrefix.'.force-delete')
                ->method('DELETE')
                ->danger()
                ->confirm('⚠️ PERMANENT: This login attempt will be deleted forever and cannot be recovered.')
                ->confirmBulk('⚠️ PERMANENT: Delete {count} login attempt(s) forever? This action CANNOT be undone!')
                ->permission('delete_'.$this->permissionPrefix)
                ->showOnStatus('trash')
                ->forBoth(),
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
                ->icon('ri-shield-keyhole-line')
                ->color('primary')
                ->default(),

            StatusTab::make('success')
                ->label('Successful')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value('success'),

            StatusTab::make('failed')
                ->label('Failed')
                ->icon('ri-close-circle-line')
                ->color('danger')
                ->value('failed'),

            StatusTab::make('blocked')
                ->label('Blocked')
                ->icon('ri-forbid-line')
                ->color('warning')
                ->value('blocked'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger')
                ->value('trash'),
        ];
    }
}
