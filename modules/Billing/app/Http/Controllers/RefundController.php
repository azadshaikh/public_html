<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Billing\Definitions\RefundDefinition;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Refund;
use Modules\Billing\Services\RefundService;
use Modules\Customers\Services\CustomerService;

class RefundController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly RefundService $refundService,
        private readonly CustomerService $customerService
    ) {}

    public static function middleware(): array
    {
        return (new RefundDefinition)->getMiddleware();
    }

    protected function service(): RefundService
    {
        return $this->refundService;
    }

    protected function getFormViewData(Model $model): array
    {
        $refund = $model instanceof Refund ? $model : new Refund;

        return [
            'initialValues' => [
                'refund_number' => $refund->refund_number ?? '',
                'reference' => $refund->reference ?? '',
                'idempotency_key' => $refund->idempotency_key ?? '',
                'payment_id' => $refund->payment_id ?? '',
                'invoice_id' => $refund->invoice_id ?? '',
                'customer_id' => $refund->customer_id ?? '',
                'amount' => $refund->amount ?? '',
                'currency' => $refund->currency ?? 'USD',
                'type' => $refund->type ?? '',
                'status' => $refund->status ?? Refund::STATUS_PENDING,
                'gateway_refund_id' => $refund->gateway_refund_id ?? '',
                'refunded_at' => $refund->refunded_at?->format('Y-m-d') ?? '',
                'failed_at' => $refund->failed_at?->format('Y-m-d') ?? '',
                'reason' => $refund->reason ?? '',
                'notes' => $refund->notes ?? '',
            ],
            'statusOptions' => $this->refundService->getStatusOptions(),
            'typeOptions' => $this->refundService->getTypeOptions(),
            'currencyOptions' => $this->refundService->getCurrencyOptions(),
            'customerOptions' => $this->customerService->getCustomerOptions(),
            'paymentOptions' => $this->getPaymentOptions(),
            'invoiceOptions' => $this->getInvoiceOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model instanceof Refund ? ($model->refund_number ?: "Refund #{$model->id}") : "#{$model->id}",
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function getPaymentOptions(): array
    {
        return Payment::query()
            ->select('id', 'payment_number')->latest()
            ->limit(200)
            ->get()
            ->map(fn (Payment $payment): array => [
                'value' => $payment->id,
                'label' => $payment->payment_number,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function getInvoiceOptions(): array
    {
        return Invoice::query()
            ->select('id', 'invoice_number')->latest()
            ->limit(200)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'value' => $invoice->id,
                'label' => $invoice->invoice_number,
            ])
            ->values()
            ->all();
    }
}
