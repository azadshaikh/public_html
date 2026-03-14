<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Models\EmailLog;
use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class EmailLogDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    /**
     * Route prefix using dot notation
     */
    protected string $routePrefix = 'app.masters.email.logs';

    /**
     * Permission prefix (used in middleware)
     */
    protected string $permissionPrefix = 'email_logs';

    protected bool $requiresSuperUserAccess = true;

    /**
     * Status field name
     */
    protected ?string $statusField = 'status';

    protected bool $enableBulkActions = false;

    /**
     * Return the Model class
     */
    public function getModelClass(): string
    {
        return EmailLog::class;
    }

    // ================================================================
    // DATAGRID COLUMNS
    // ================================================================

    public function columns(): array
    {
        return [
            // ⚠️ Bulk select checkbox - ALWAYS FIRST
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            // Subject with template preview - links to show page
            Column::make('subject')
                ->label('Subject')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('300px'),

            // Provider name
            Column::make('provider_name')
                ->label('Provider')
                ->sortable()
                ->width('150px'),

            // Status with badge
            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            // Sent date
            Column::make('sent_at')
                ->label('Sent At')
                ->sortable()
                ->width('180px'),

            // ⚠️ Actions column - ALWAYS LAST
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
            // Provider filter
            Filter::select('email_provider_id')
                ->label('Provider')
                ->options([]) // Will be populated by controller
                ->placeholder('All Providers'),

            // Template filter
            Filter::select('email_template_id')
                ->label('Template')
                ->options([]) // Will be populated by controller
                ->placeholder('All Templates'),

            // Date range filter
            Filter::dateRange('sent_at')
                ->label('Sent Date'),
        ];
    }

    // ================================================================
    // ROW & BULK ACTIONS
    // ================================================================

    public function actions(): array
    {
        return [
            // --------------------------------------------------------
            // ROW-ONLY ACTIONS (Read-only entity - no edit/delete)
            // --------------------------------------------------------

            Action::make('show')
                ->label('View')
                ->icon('ri-eye-line')
                ->route($this->routePrefix.'.show')
                ->permission('view_'.$this->permissionPrefix)
                ->forRow(),
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
                ->icon('ri-mail-line')
                ->color('primary')
                ->default(),

            StatusTab::make('sent')
                ->label('Sent')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value('sent'),

            StatusTab::make('failed')
                ->label('Failed')
                ->icon('ri-error-warning-line')
                ->color('danger')
                ->value('failed'),

            StatusTab::make('queued')
                ->label('Queued')
                ->icon('ri-time-line')
                ->color('warning')
                ->value('queued'),
        ];
    }
}
