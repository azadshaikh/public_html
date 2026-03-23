<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Subscriptions\Http\Requests\PlanRequest;
use Modules\Subscriptions\Models\Plan;

class PlanDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'subscriptions.plans';

    protected string $permissionPrefix = 'subscriptions.plans';

    /**
     * Status field is null because is_active is a boolean handled in PlanResource.
     */
    protected ?string $statusField = null;

    public function getModelClass(): string
    {
        return Plan::class;
    }

    public function getRequestClass(): ?string
    {
        return PlanRequest::class;
    }

    // ================================================================
    // DATAGRID COLUMNS
    // ================================================================

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('name')
                ->label('Plan Name')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('180px'),

            Column::make('prices_summary')
                ->label('Pricing')
                ->width('200px'),

            Column::make('trial_days')
                ->label('Trial Days')
                ->sortable()
                ->width('100px'),

            Column::make('subscriptions_count')
                ->label('Subscribers')
                ->sortable()
                ->width('100px'),

            Column::make('is_active')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('100px'),

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
                ->icon('ri-close-circle-line')
                ->color('warning')
                ->value('inactive'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }
}
