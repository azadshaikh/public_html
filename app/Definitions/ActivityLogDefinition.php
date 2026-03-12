<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Models\ActivityLog;
use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class ActivityLogDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    /**
     * Route prefix using dot notation
     */
    protected string $routePrefix = 'app.logs.activity-logs';

    /**
     * Permission prefix (used in middleware)
     */
    protected string $permissionPrefix = 'activity_logs';

    /**
     * Status field name (null since we use soft deletes for status)
     */
    protected ?string $statusField = null;

    protected bool $enableBulkActions = true;

    /**
     * Return the Model class
     */
    public function getModelClass(): string
    {
        return ActivityLog::class;
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

            // Action/Event badge
            Column::make('event')
                ->label('Action')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            // Description - links to show page
            Column::make('description')
                ->label('Description')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('300px'),

            // User who performed the action
            Column::make('causer_name')
                ->label('User')
                ->sortable()
                ->width('150px'),

            // Subject/Model affected
            Column::make('subject_display')
                ->label('Subject')
                ->width('200px'),

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
            // Action/Event filter
            Filter::select('event')
                ->label('Action')
                ->options([]) // Will be populated by service
                ->placeholder('All Actions'),

            // User filter
            Filter::select('causer_id')
                ->label('User')
                ->options([]) // Will be populated by service
                ->placeholder('All Users'),

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
                ->confirm('Are you sure you want to delete this activity log?')
                ->confirmBulk('Delete {count} activity log(s)?')
                ->hideOnStatus('trash')
                ->forBoth(),

            // Restore action (row + bulk, trash only)
            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->route($this->routePrefix.'.restore')
                ->method('PATCH')
                ->success()
                ->confirm('Restore this activity log?')
                ->confirmBulk('Restore {count} activity log(s)?')
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
                ->confirm('⚠️ PERMANENT: This activity log will be deleted forever and cannot be recovered.')
                ->confirmBulk('⚠️ PERMANENT: Delete {count} activity log(s) forever? This action CANNOT be undone!')
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
                ->label('All Logs')
                ->icon('ri-history-line')
                ->color('primary')
                ->default(),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger')
                ->value('trash'),
        ];
    }

    // ================================================================
    // DATAGRID CONFIGURATION (Override for read-only entity)
    // ================================================================

    /**
     * Export configuration for DataGrid JavaScript
     * Override to exclude create route for read-only entity
     */
    public function toDataGridConfig(): array
    {
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
                ->filter(fn (Action $action): bool => $action->authorized())
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
            'routes' => [
                'index' => route($this->getIndexRoute()),
                'bulkAction' => route($this->routePrefix.'.bulk-action'),
                // No create route for read-only entity
            ],
        ];
    }
}
