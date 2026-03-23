<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Definitions\InvoiceDefinition;
use Modules\Billing\Models\Invoice;

class InvoiceRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'invoice_number' => ['nullable', 'string', 'max:50', $this->uniqueRule('invoice_number')],
            'reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['required', 'integer', 'exists:customers_customers,id'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['required', Rule::in([
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_PENDING,
                Invoice::STATUS_SENT,
                Invoice::STATUS_PAID,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
                Invoice::STATUS_CANCELLED,
                Invoice::STATUS_REFUNDED,
            ]),
            ],
            'payment_status' => ['required', Rule::in([
                Invoice::PAYMENT_STATUS_UNPAID,
                Invoice::PAYMENT_STATUS_PARTIAL,
                Invoice::PAYMENT_STATUS_PAID,
                Invoice::PAYMENT_STATUS_REFUNDED,
            ]),
            ],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.invoiceable_id' => ['nullable', 'integer'],
            'items.*.invoiceable_type' => ['nullable', 'string', 'max:255'],
            'items.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'invoice_number' => 'Invoice Number',
            'customer_id' => 'Customer',
            'billing_name' => 'Billing Name',
            'billing_email' => 'Billing Email',
            'billing_phone' => 'Billing Phone',
            'billing_address' => 'Billing Address',
            'issue_date' => 'Issue Date',
            'due_date' => 'Due Date',
            'payment_status' => 'Payment Status',
            'items' => 'Invoice Items',
            'items.*.name' => 'Item Name',
            'items.*.quantity' => 'Quantity',
            'items.*.unit_price' => 'Unit Price',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Please add at least one invoice item.',
            'items.min' => 'Please add at least one invoice item.',
            'items.*.name.required' => 'Each item must have a name.',
            'items.*.quantity.required' => 'Each item must have a quantity.',
            'items.*.unit_price.required' => 'Each item must have a unit price.',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new InvoiceDefinition;
    }

    protected function getModelClass(): string
    {
        return Invoice::class;
    }
}
