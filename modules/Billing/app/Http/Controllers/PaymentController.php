<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Billing\Definitions\PaymentDefinition;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\PaymentService;
use Modules\Customers\Services\CustomerService;

class PaymentController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly CustomerService $customerService
    ) {}

    public static function middleware(): array
    {
        return (new PaymentDefinition)->getMiddleware();
    }

    protected function service(): PaymentService
    {
        return $this->paymentService;
    }

    protected function getFormViewData(Model $model): array
    {
        $payment = $model instanceof Payment ? $model : new Payment;

        return [
            'initialValues' => [
                'payment_number' => $payment->payment_number ?? '',
                'reference' => $payment->reference ?? '',
                'idempotency_key' => $payment->idempotency_key ?? '',
                'invoice_id' => $payment->invoice_id ?? '',
                'customer_id' => $payment->customer_id ?? '',
                'amount' => $payment->amount ?? '',
                'currency' => $payment->currency ?? 'USD',
                'exchange_rate' => $payment->exchange_rate ?? 1,
                'payment_method' => $payment->payment_method ?? '',
                'payment_gateway' => $payment->payment_gateway ?? '',
                'status' => $payment->status ?? Payment::STATUS_PENDING,
                'gateway_transaction_id' => $payment->gateway_transaction_id ?? '',
                'paid_at' => $payment->paid_at?->format('Y-m-d') ?? '',
                'failed_at' => $payment->failed_at?->format('Y-m-d') ?? '',
                'notes' => $payment->notes ?? '',
            ],
            'statusOptions' => $this->paymentService->getStatusOptions(),
            'methodOptions' => $this->paymentService->getPaymentMethodOptions(),
            'gatewayOptions' => $this->paymentService->getGatewayOptions(),
            'currencyOptions' => $this->paymentService->getCurrencyOptions(),
            'customerOptions' => $this->customerService->getCustomerOptions(),
            'invoiceOptions' => $this->getInvoiceOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model instanceof Payment ? ($model->payment_number ?: "Payment #{$model->id}") : "#{$model->id}",
        ];
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
