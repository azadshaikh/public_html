<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Definitions\PaymentDefinition;
use Modules\Billing\Models\Payment;

class PaymentRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'payment_number' => ['nullable', 'string', 'max:50', $this->uniqueRule('payment_number')],
            'reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'invoice_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in([
                Payment::METHOD_CARD,
                Payment::METHOD_BANK_TRANSFER,
                Payment::METHOD_CASH,
                Payment::METHOD_CHECK,
                Payment::METHOD_PAYPAL,
                Payment::METHOD_OTHER,
            ]),
            ],
            'payment_gateway' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::in([
                Payment::STATUS_PENDING,
                Payment::STATUS_PROCESSING,
                Payment::STATUS_COMPLETED,
                Payment::STATUS_FAILED,
                Payment::STATUS_CANCELLED,
                Payment::STATUS_REFUNDED,
            ]),
            ],
            'gateway_transaction_id' => ['nullable', 'string', 'max:255'],
            'gateway_response' => ['nullable', 'array'],
            'paid_at' => ['nullable', 'date'],
            'failed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_number' => 'Payment Number',
            'invoice_id' => 'Invoice',
            'customer_id' => 'Customer',
            'payment_method' => 'Payment Method',
            'payment_gateway' => 'Payment Gateway',
            'paid_at' => 'Paid At',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new PaymentDefinition;
    }

    protected function getModelClass(): string
    {
        return Payment::class;
    }
}
