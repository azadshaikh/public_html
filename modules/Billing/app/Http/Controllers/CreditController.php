<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Billing\Definitions\CreditDefinition;
use Modules\Billing\Models\Credit;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\CreditService;
use Modules\Customers\Services\CustomerService;

class CreditController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly CreditService $creditService,
        private readonly CustomerService $customerService
    ) {}

    public static function middleware(): array
    {
        return (new CreditDefinition)->getMiddleware();
    }

    protected function service(): CreditService
    {
        return $this->creditService;
    }

    protected function getFormViewData(Model $model): array
    {
        $credit = $model instanceof Credit ? $model : new Credit;

        return [
            'initialValues' => [
                'credit_number' => $credit->credit_number ?? '',
                'reference' => $credit->reference ?? '',
                'customer_id' => $credit->customer_id ?? '',
                'invoice_id' => $credit->invoice_id ?? '',
                'amount' => $credit->amount ?? '',
                'amount_used' => $credit->amount_used ?? 0,
                'amount_remaining' => $credit->amount_remaining ?? '',
                'currency' => $credit->currency ?? 'USD',
                'type' => $credit->type ?? '',
                'status' => $credit->status ?? Credit::STATUS_ACTIVE,
                'expires_at' => $credit->expires_at?->format('Y-m-d') ?? '',
                'reason' => $credit->reason ?? '',
                'notes' => $credit->notes ?? '',
            ],
            'statusOptions' => $this->creditService->getStatusOptions(),
            'typeOptions' => $this->creditService->getTypeOptions(),
            'currencyOptions' => $this->creditService->getCurrencyOptions(),
            'customerOptions' => $this->customerService->getCustomerOptions(),
            'invoiceOptions' => $this->getInvoiceOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model instanceof Credit ? ($model->credit_number ?: "Credit #{$model->id}") : "#{$model->id}",
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
