<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Arr;
use Modules\Billing\Definitions\InvoiceDefinition;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\InvoiceService;
use Modules\Customers\Services\CustomerService;

class InvoiceController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly CustomerService $customerService
    ) {}

    public static function middleware(): array
    {
        return (new InvoiceDefinition)->getMiddleware();
    }

    protected function service(): InvoiceService
    {
        return $this->invoiceService;
    }

    protected function getFormViewData(Model $model): array
    {
        $invoice = $model instanceof Invoice ? $model : new Invoice;

        return [
            'initialValues' => [
                'invoice_number' => $invoice->invoice_number ?? '',
                'reference' => $invoice->reference ?? '',
                'customer_id' => $invoice->customer_id ?? '',
                'billing_name' => $invoice->billing_name ?? '',
                'billing_email' => $invoice->billing_email ?? '',
                'billing_phone' => $invoice->billing_phone ?? '',
                'billing_address' => $invoice->billing_address ?? '',
                'currency' => $invoice->currency ?? 'USD',
                'exchange_rate' => $invoice->exchange_rate ?? 1,
                'issue_date' => $invoice->issue_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d'),
                'status' => $invoice->status ?? Invoice::STATUS_DRAFT,
                'payment_status' => $invoice->payment_status ?? Invoice::PAYMENT_STATUS_UNPAID,
                'paid_at' => $invoice->paid_at?->format('Y-m-d') ?? '',
                'notes' => $invoice->notes ?? '',
                'terms' => $invoice->terms ?? '',
                'items' => $this->getLineItems($invoice),
            ],
            'statusOptions' => $this->invoiceService->getStatusOptions(),
            'paymentStatusOptions' => $this->invoiceService->getPaymentStatusOptions(),
            'currencyOptions' => $this->invoiceService->getCurrencyOptions(),
            'customerOptions' => $this->customerService->getCustomerOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model instanceof Invoice ? ($model->invoice_number ?: "Invoice #{$model->id}") : "#{$model->id}",
        ];
    }

    protected function handleCreationSideEffects(Model $model): void
    {
        if ($model instanceof Invoice) {
            $this->syncInvoiceItems($model);
        }
    }

    protected function handleUpdateSideEffects(Model $model): void
    {
        if ($model instanceof Invoice) {
            $this->syncInvoiceItems($model);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getLineItems(Invoice $invoice): array
    {
        if (! $invoice->exists) {
            return [[
                'id' => null,
                'name' => '',
                'description' => '',
                'quantity' => 1,
                'unit_price' => 0,
                'tax_rate' => 0,
                'discount_rate' => 0,
                'sort_order' => 0,
            ],
            ];
        }

        return $invoice->items
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_rate' => (float) $item->tax_rate,
                'discount_rate' => (float) $item->discount_rate,
                'sort_order' => (int) $item->sort_order,
            ])
            ->all();
    }

    private function syncInvoiceItems(Invoice $invoice): void
    {
        $items = request()->input('items', []);
        $existingItems = $invoice->items()->get()->keyBy('id');
        $submittedIds = [];

        foreach ($items as $index => $itemData) {
            $payload = Arr::only($itemData, [
                'name',
                'description',
                'quantity',
                'unit_price',
                'tax_rate',
                'discount_rate',
                'invoiceable_id',
                'invoiceable_type',
                'metadata',
            ]);
            $payload['sort_order'] = $index;

            $itemId = $itemData['id'] ?? null;

            if ($itemId && $existingItems->has($itemId)) {
                $existingItems[$itemId]->fill($payload)->save();
                $submittedIds[] = (int) $itemId;

                continue;
            }

            $invoice->items()->create($payload);
        }

        $itemsToDelete = $existingItems
            ->keys()
            ->diff($submittedIds)
            ->all();

        if (! empty($itemsToDelete)) {
            $invoice->items()
                ->whereIn('id', $itemsToDelete)
                ->get()
                ->each
                ->delete();
        }

        $invoice->refresh()->recalculateTotals();
    }
}
