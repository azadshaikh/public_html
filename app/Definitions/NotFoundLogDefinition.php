<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Models\NotFoundLog;
use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class NotFoundLogDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    /**
     * Route prefix using dot notation
     */
    protected string $routePrefix = 'app.logs.not-found-logs';

    /**
     * Permission prefix (used in middleware)
     */
    protected string $permissionPrefix = 'not_found_logs';

    /**
     * Status field name - null since we use is_bot/is_suspicious flags
     */
    protected ?string $statusField = null;

    protected bool $includeActionConfigInInertia = false;

    protected bool $includeEmptyStateConfigInInertia = false;

    protected bool $enableBulkActions = true;

    /**
     * Return the Model class
     */
    public function getModelClass(): string
    {
        return NotFoundLog::class;
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

            // Status indicators
            Column::make('status_badge')
                ->label('Type')
                ->template('badge')
                ->width('100px'),

            // URL
            Column::make('url')
                ->label('URL')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('300px'),

            // Referer
            Column::make('referer_display')
                ->label('Referer')
                ->width('200px'),

            // IP Address
            Column::make('ip_address')
                ->label('IP Address')
                ->sortable()
                ->searchable()
                ->width('130px'),

            // Method
            Column::make('method')
                ->label('Method')
                ->width('80px'),

            // User
            Column::make('user_name')
                ->label('User')
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
                ->confirm('Are you sure you want to delete this 404 log?')
                ->confirmBulk('Delete {count} 404 log(s)?')
                ->hideOnStatus('trash')
                ->forBoth(),

            // Restore action (row + bulk, trash only)
            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->route($this->routePrefix.'.restore')
                ->method('PATCH')
                ->success()
                ->confirm('Restore this 404 log?')
                ->confirmBulk('Restore {count} 404 log(s)?')
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
                ->confirm('⚠️ PERMANENT: This 404 log will be deleted forever and cannot be recovered.')
                ->confirmBulk('⚠️ PERMANENT: Delete {count} 404 log(s) forever? This action CANNOT be undone!')
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
                ->icon('ri-file-warning-line')
                ->color('primary')
                ->default(),

            StatusTab::make('suspicious')
                ->label('Suspicious')
                ->icon('ri-alarm-warning-line')
                ->color('danger')
                ->value('suspicious'),

            StatusTab::make('bots')
                ->label('Bots')
                ->icon('ri-robot-line')
                ->color('warning')
                ->value('bots'),

            StatusTab::make('human')
                ->label('Human')
                ->icon('ri-user-line')
                ->color('success')
                ->value('human'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger')
                ->value('trash'),
        ];
    }
}
