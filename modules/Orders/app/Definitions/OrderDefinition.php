<?php

declare(strict_types=1);

namespace Modules\Orders\Definitions;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Orders\Models\Order;

class OrderDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'app.orders';

    protected string $permissionPrefix = 'orders';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Order::class;
    }

    public function getRequestClass(): ?string
    {
        return null; // Orders are system-generated — no user-facing create/edit form
    }

    // Orders are system-generated; disable the create button
    public function getCreateRoute(): ?string
    {
        return null;
    }

    public function getEditRoute(): ?string
    {
        return null;
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

            Column::make('order_number')
                ->label('Order #')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('160px'),

            Column::make('customer_display')
                ->label('Customer')
                ->searchable(),

            Column::make('type')
                ->label('Type')
                ->template('badge'),

            Column::make('status')
                ->label('Status')
                ->template('badge'),

            Column::make('total_display')
                ->label('Total')
                ->sortable()
                ->width('110px'),

            Column::make('paid_at')
                ->label('Paid At')
                ->template('datetime')
                ->sortable(),

            Column::make('created_at')
                ->label('Created')
                ->template('datetime')
                ->sortable(),

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
            Filter::select('type')
                ->label('Type')
                ->options([
                    ['value' => Order::TYPE_SUBSCRIPTION_SIGNUP,  'label' => 'Subscription Signup'],
                    ['value' => Order::TYPE_SUBSCRIPTION_UPGRADE, 'label' => 'Subscription Upgrade'],
                    ['value' => Order::TYPE_ADDON,                'label' => 'Add-on'],
                    ['value' => Order::TYPE_ONE_TIME,             'label' => 'One-Time'],
                ])
                ->placeholder('All Types'),

            Filter::dateRange('created_at')
                ->label('Created Date'),

            Filter::dateRange('paid_at')
                ->label('Paid Date'),
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

            StatusTab::make('pending')
                ->label('Pending')
                ->icon('ri-time-line')
                ->color('warning')
                ->value(Order::STATUS_PENDING),

            StatusTab::make('processing')
                ->label('Processing')
                ->icon('ri-loader-line')
                ->color('info')
                ->value(Order::STATUS_PROCESSING),

            StatusTab::make('active')
                ->label('Active')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value(Order::STATUS_ACTIVE),

            StatusTab::make('cancelled')
                ->label('Cancelled')
                ->icon('ri-close-circle-line')
                ->color('danger')
                ->value(Order::STATUS_CANCELLED),

            StatusTab::make('refunded')
                ->label('Refunded')
                ->icon('ri-refund-2-line')
                ->color('secondary')
                ->value(Order::STATUS_REFUNDED),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    // ================================================================
    // ACTIONS (show + delete only — orders are system-generated)
    // ================================================================

    public function actions(): array
    {
        $permissionPrefix = $this->getPermissionPrefix();

        return [
            Action::make('show')
                ->label('View')
                ->icon('ri-eye-line')
                ->route($this->getRoutePrefix().'.show')
                ->permission('view_'.$permissionPrefix)
                ->forRow(),

            Action::make('delete')
                ->label('Delete')
                ->icon('ri-delete-bin-line')
                ->danger()
                ->route($this->getRoutePrefix().'.destroy')
                ->method('DELETE')
                ->confirm('Are you sure you want to trash this order?')
                ->confirmBulk('Move {count} orders to trash?')
                ->permission('delete_'.$permissionPrefix)
                ->hideOnStatus('trash')
                ->forBoth(),

            Action::make('restore')
                ->label('Restore')
                ->icon('ri-arrow-go-back-line')
                ->success()
                ->route($this->getRoutePrefix().'.restore')
                ->method('PATCH')
                ->confirm('Are you sure you want to restore this order?')
                ->confirmBulk('Restore {count} orders?')
                ->permission('delete_'.$permissionPrefix)
                ->showOnStatus('trash')
                ->forBoth(),

            Action::make('force_delete')
                ->label('Force Delete')
                ->icon('ri-delete-bin-2-line')
                ->danger()
                ->route($this->getRoutePrefix().'.force-delete')
                ->method('DELETE')
                ->confirm('Permanently delete this order? This cannot be undone.')
                ->confirmBulk('Permanently delete {count} orders? This cannot be undone.')
                ->permission('delete_'.$permissionPrefix)
                ->showOnStatus('trash')
                ->forBoth(),
        ];
    }
}
