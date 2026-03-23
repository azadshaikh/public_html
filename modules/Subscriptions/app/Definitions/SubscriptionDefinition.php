<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Subscriptions\Http\Requests\SubscriptionRequest;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;

class SubscriptionDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'subscriptions.subscriptions';

    protected string $permissionPrefix = 'subscriptions.subscriptions';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Subscription::class;
    }

    public function getRequestClass(): ?string
    {
        return SubscriptionRequest::class;
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

            Column::make('unique_id')
                ->label('ID')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('120px'),

            Column::make('subscriber_name')
                ->label('Subscriber')
                ->sortable()
                ->searchable()
                ->link('subscriber_url')
                ->width('180px'),

            Column::make('plan_name')
                ->label('Plan')
                ->sortable()
                ->searchable()
                ->width('150px'),

            Column::make('formatted_price')
                ->label('Price')
                ->width('100px'),

            Column::make('billing_cycle')
                ->label('Cycle')
                ->width('100px'),

            Column::make('current_period_end')
                ->label('Renews On')
                ->sortable()
                ->template('date')
                ->width('120px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('100px'),

            Column::make('created_at')
                ->label('Started')
                ->sortable()
                ->template('date')
                ->width('120px'),

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
            Filter::select('plan_id')
                ->label('Plan')
                ->options($this->getPlanOptions())
                ->placeholder('All Plans'),

            Filter::dateRange('current_period_end')
                ->label('Renewal Date'),

            Filter::dateRange('created_at')
                ->label('Start Date'),
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

            StatusTab::make(Subscription::STATUS_ACTIVE)
                ->label('Active')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value(Subscription::STATUS_ACTIVE),

            StatusTab::make(Subscription::STATUS_TRIALING)
                ->label('Trial')
                ->icon('ri-time-line')
                ->color('info')
                ->value(Subscription::STATUS_TRIALING),

            StatusTab::make(Subscription::STATUS_PAST_DUE)
                ->label('Past Due')
                ->icon('ri-error-warning-line')
                ->color('warning')
                ->value(Subscription::STATUS_PAST_DUE),

            StatusTab::make(Subscription::STATUS_CANCELED)
                ->label('Canceled')
                ->icon('ri-close-circle-line')
                ->color('warning')
                ->value(Subscription::STATUS_CANCELED),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    /**
     * Get plan options for filter.
     */
    protected function getPlanOptions(): array
    {
        return Plan::query()
            ->select('id', 'name')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($plan): array => ['value' => (string) $plan->id, 'label' => $plan->name])
            ->all();
    }
}
