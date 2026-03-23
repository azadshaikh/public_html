<?php

declare(strict_types=1);

namespace Modules\Billing\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Billing\Http\Requests\InvoiceRequest;
use Modules\Billing\Models\Invoice;

class InvoiceDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'app.billing.invoices';

    protected string $permissionPrefix = 'invoices';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Invoice::class;
    }

    public function getRequestClass(): ?string
    {
        return InvoiceRequest::class;
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

            Column::make('invoice_number')
                ->label('Invoice #')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('140px'),

            Column::make('customer_display')
                ->label('Customer')
                ->width('200px'),

            Column::make('formatted_total')
                ->label('Total')
                ->sortable('total')
                ->width('120px'),

            Column::make('formatted_amount_due')
                ->label('Amount Due')
                ->sortable('amount_due')
                ->width('120px'),

            Column::make('payment_status')
                ->label('Payment')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('due_date')
                ->label('Due Date')
                ->template('date')
                ->sortable()
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
            Filter::select('payment_status')
                ->label('Payment Status')
                ->options([
                    ['value' => Invoice::PAYMENT_STATUS_UNPAID, 'label' => 'Unpaid'],
                    ['value' => Invoice::PAYMENT_STATUS_PARTIAL, 'label' => 'Partial'],
                    ['value' => Invoice::PAYMENT_STATUS_PAID, 'label' => 'Paid'],
                    ['value' => Invoice::PAYMENT_STATUS_REFUNDED, 'label' => 'Refunded'],
                ])
                ->placeholder('All Payment Statuses'),

            Filter::dateRange('issue_date')
                ->label('Issue Date'),

            Filter::dateRange('due_date')
                ->label('Due Date'),
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

            StatusTab::make(Invoice::STATUS_DRAFT)
                ->label('Draft')
                ->icon('ri-file-edit-line')
                ->color('secondary')
                ->value(Invoice::STATUS_DRAFT),

            StatusTab::make(Invoice::STATUS_PENDING)
                ->label('Pending')
                ->icon('ri-time-line')
                ->color('warning')
                ->value(Invoice::STATUS_PENDING),

            StatusTab::make(Invoice::STATUS_SENT)
                ->label('Sent')
                ->icon('ri-send-plane-line')
                ->color('info')
                ->value(Invoice::STATUS_SENT),

            StatusTab::make(Invoice::STATUS_PARTIAL)
                ->label('Partial')
                ->icon('ri-pie-chart-line')
                ->color('primary')
                ->value(Invoice::STATUS_PARTIAL),

            StatusTab::make(Invoice::STATUS_PAID)
                ->label('Paid')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value(Invoice::STATUS_PAID),

            StatusTab::make(Invoice::STATUS_OVERDUE)
                ->label('Overdue')
                ->icon('ri-error-warning-line')
                ->color('danger')
                ->value(Invoice::STATUS_OVERDUE),

            StatusTab::make(Invoice::STATUS_CANCELLED)
                ->label('Cancelled')
                ->icon('ri-close-circle-line')
                ->color('dark')
                ->value(Invoice::STATUS_CANCELLED),

            StatusTab::make(Invoice::STATUS_REFUNDED)
                ->label('Refunded')
                ->icon('ri-arrow-go-back-line')
                ->color('warning')
                ->value(Invoice::STATUS_REFUNDED),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    // ================================================================
    // VIEW CONFIGURATION
    // ================================================================

    public function getViewPath(): string
    {
        return 'billing::invoices';
    }
}
