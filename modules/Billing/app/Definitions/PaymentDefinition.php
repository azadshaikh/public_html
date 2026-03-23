<?php

declare(strict_types=1);

namespace Modules\Billing\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Billing\Http\Requests\PaymentRequest;
use Modules\Billing\Models\Payment;

class PaymentDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.billing.payments';

    protected string $permissionPrefix = 'payments';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Payment::class;
    }

    public function getRequestClass(): ?string
    {
        return PaymentRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('payment_number')
                ->label('Payment #')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('150px'),

            Column::make('invoice_number')
                ->label('Invoice #')
                ->width('140px'),

            Column::make('customer_display')
                ->label('Customer')
                ->width('200px'),

            Column::make('formatted_amount')
                ->label('Amount')
                ->sortable('amount')
                ->width('120px'),

            Column::make('payment_method')
                ->label('Method')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('paid_at')
                ->label('Paid At')
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
            Filter::select('payment_method')
                ->label('Payment Method')
                ->options([
                    ['value' => Payment::METHOD_CARD, 'label' => 'Credit Card'],
                    ['value' => Payment::METHOD_BANK_TRANSFER, 'label' => 'Bank Transfer'],
                    ['value' => Payment::METHOD_CASH, 'label' => 'Cash'],
                    ['value' => Payment::METHOD_CHECK, 'label' => 'Check'],
                    ['value' => Payment::METHOD_PAYPAL, 'label' => 'PayPal'],
                    ['value' => Payment::METHOD_OTHER, 'label' => 'Other'],
                ])
                ->placeholder('All Methods'),

            Filter::select('payment_gateway')
                ->label('Gateway')
                ->options([
                    ['value' => 'stripe', 'label' => 'Stripe'],
                    ['value' => 'manual', 'label' => 'Manual'],
                ])
                ->placeholder('All Gateways'),

            Filter::dateRange('paid_at')
                ->label('Paid Date'),
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

            StatusTab::make(Payment::STATUS_PENDING)
                ->label('Pending')
                ->icon('ri-time-line')
                ->color('warning')
                ->value(Payment::STATUS_PENDING),

            StatusTab::make(Payment::STATUS_PROCESSING)
                ->label('Processing')
                ->icon('ri-loader-4-line')
                ->color('info')
                ->value(Payment::STATUS_PROCESSING),

            StatusTab::make(Payment::STATUS_COMPLETED)
                ->label('Completed')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value(Payment::STATUS_COMPLETED),

            StatusTab::make(Payment::STATUS_FAILED)
                ->label('Failed')
                ->icon('ri-close-circle-line')
                ->color('danger')
                ->value(Payment::STATUS_FAILED),

            StatusTab::make(Payment::STATUS_CANCELLED)
                ->label('Cancelled')
                ->icon('ri-close-circle-line')
                ->color('dark')
                ->value(Payment::STATUS_CANCELLED),

            StatusTab::make(Payment::STATUS_REFUNDED)
                ->label('Refunded')
                ->icon('ri-arrow-go-back-line')
                ->color('secondary')
                ->value(Payment::STATUS_REFUNDED),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    public function getViewPath(): string
    {
        return 'billing::payments';
    }
}
