<?php

declare(strict_types=1);

namespace Modules\Billing\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Billing\Http\Requests\RefundRequest;
use Modules\Billing\Models\Refund;

class RefundDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.billing.refunds';

    protected string $permissionPrefix = 'refunds';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Refund::class;
    }

    public function getRequestClass(): ?string
    {
        return RefundRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('refund_number')
                ->label('Refund #')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('150px'),

            Column::make('payment_number')
                ->label('Payment #')
                ->width('140px'),

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

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('refunded_at')
                ->label('Refunded At')
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
                ->label('Refund Type')
                ->options([
                    ['value' => Refund::TYPE_FULL, 'label' => 'Full'],
                    ['value' => Refund::TYPE_PARTIAL, 'label' => 'Partial'],
                ])
                ->placeholder('All Types'),

            Filter::dateRange('refunded_at')
                ->label('Refund Date'),
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

            StatusTab::make(Refund::STATUS_PENDING)
                ->label('Pending')
                ->icon('ri-time-line')
                ->color('warning')
                ->value(Refund::STATUS_PENDING),

            StatusTab::make(Refund::STATUS_PROCESSING)
                ->label('Processing')
                ->icon('ri-loader-4-line')
                ->color('info')
                ->value(Refund::STATUS_PROCESSING),

            StatusTab::make(Refund::STATUS_COMPLETED)
                ->label('Completed')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value(Refund::STATUS_COMPLETED),

            StatusTab::make(Refund::STATUS_FAILED)
                ->label('Failed')
                ->icon('ri-close-circle-line')
                ->color('danger')
                ->value(Refund::STATUS_FAILED),

            StatusTab::make(Refund::STATUS_CANCELLED)
                ->label('Cancelled')
                ->icon('ri-close-circle-line')
                ->color('dark')
                ->value(Refund::STATUS_CANCELLED),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    public function getViewPath(): string
    {
        return 'billing::refunds';
    }
}
