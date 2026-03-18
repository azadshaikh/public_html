<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Http\Requests\EmailProviderRequest;
use App\Models\EmailProvider;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class EmailProviderDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    /**
     * Route prefix using dot notation
     */
    protected string $routePrefix = 'app.masters.email.providers';

    /**
     * Permission prefix (used in middleware)
     */
    protected string $permissionPrefix = 'email_providers';

    protected bool $requiresSuperUserAccess = true;

    /**
     * Status field name
     */
    protected ?string $statusField = 'status';

    protected bool $includeActionConfigInInertia = false;

    protected bool $includeEmptyStateConfigInInertia = false;

    protected bool $includeRowActionsInInertiaRows = false;

    /**
     * Return the Model class
     */
    public function getModelClass(): string
    {
        return EmailProvider::class;
    }

    public function getRequestClass(): ?string
    {
        return EmailProviderRequest::class;
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

            // Primary identifier - links to show page
            Column::make('name')
                ->label('Name')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('200px'),

            // Sender information
            Column::make('sender_email')
                ->label('Sender Email')
                ->sortable()
                ->searchable(),

            // SMTP Host
            Column::make('smtp_host')
                ->label('SMTP Host')
                ->sortable(),

            // Status with badge
            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable(),

            // Order
            Column::make('order')
                ->label('Order')
                ->sortable()
                ->width('80px'),

            // Created date
            Column::make('created_at')
                ->label('Created')
                ->sortable(),

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
        // Note: Status filtering is handled by statusTabs(), not here.
        // Adding a status filter here would conflict with tab-based filtering.
        return [
            // Date range filter
            Filter::dateRange('created_at')
                ->label('Created Date'),
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
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value('active'),

            StatusTab::make('inactive')
                ->label('Inactive')
                ->icon('ri-pause-circle-line')
                ->color('warning')
                ->value('inactive'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }
}
