<?php

declare(strict_types=1);

namespace Modules\Billing\Definitions;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Billing\Models\Transaction;

class TransactionDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.billing.transactions';

    protected string $permissionPrefix = 'transactions';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Transaction::class;
    }

    public function getRequestClass(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            Column::make('transaction_id')
                ->label('Transaction #')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('160px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('formatted_amount')
                ->label('Amount')
                ->sortable('amount')
                ->width('120px'),

            Column::make('customer_display')
                ->label('Customer')
                ->width('200px'),

            Column::make('payment_method')
                ->label('Method')
                ->template('badge')
                ->width('120px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('created_at')
                ->label('Created')
                ->template('datetime')
                ->sortable()
                ->width('160px'),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('type')
                ->label('Type')
                ->options([
                    ['value' => Transaction::TYPE_INVOICE, 'label' => 'Invoice'],
                    ['value' => Transaction::TYPE_PAYMENT, 'label' => 'Payment'],
                    ['value' => Transaction::TYPE_REFUND, 'label' => 'Refund'],
                    ['value' => Transaction::TYPE_CREDIT, 'label' => 'Credit'],
                    ['value' => Transaction::TYPE_DEBIT, 'label' => 'Debit'],
                    ['value' => Transaction::TYPE_ADJUSTMENT, 'label' => 'Adjustment'],
                ])
                ->placeholder('All Types'),

            Filter::select('payment_method')
                ->label('Method')
                ->options([
                    ['value' => 'card', 'label' => 'Card'],
                    ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
                    ['value' => 'cash', 'label' => 'Cash'],
                    ['value' => 'check', 'label' => 'Check'],
                    ['value' => 'paypal', 'label' => 'PayPal'],
                    ['value' => 'other', 'label' => 'Other'],
                ])
                ->placeholder('All Methods'),

            Filter::dateRange('created_at')
                ->label('Created Date'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-list-check')
                ->color('primary')
                ->default(),

            StatusTab::make(Transaction::STATUS_COMPLETED)
                ->label('Completed')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value(Transaction::STATUS_COMPLETED),

            StatusTab::make(Transaction::STATUS_PENDING)
                ->label('Pending')
                ->icon('ri-time-line')
                ->color('warning')
                ->value(Transaction::STATUS_PENDING),

            StatusTab::make(Transaction::STATUS_FAILED)
                ->label('Failed')
                ->icon('ri-close-circle-line')
                ->color('danger')
                ->value(Transaction::STATUS_FAILED),

            StatusTab::make(Transaction::STATUS_CANCELLED)
                ->label('Cancelled')
                ->icon('ri-close-circle-line')
                ->color('dark')
                ->value(Transaction::STATUS_CANCELLED),
        ];
    }

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
        ];
    }

    public function getViewPath(): string
    {
        return 'billing::transactions';
    }
}
