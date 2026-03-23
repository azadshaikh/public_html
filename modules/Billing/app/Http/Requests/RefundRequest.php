<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Definitions\RefundDefinition;
use Modules\Billing\Models\Refund;

class RefundRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'refund_number' => ['nullable', 'string', 'max:50', $this->uniqueRule('refund_number')],
            'reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'payment_id' => ['required', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'type' => ['required', Rule::in([
                Refund::TYPE_FULL,
                Refund::TYPE_PARTIAL,
            ]),
            ],
            'status' => ['required', Rule::in([
                Refund::STATUS_PENDING,
                Refund::STATUS_PROCESSING,
                Refund::STATUS_COMPLETED,
                Refund::STATUS_FAILED,
                Refund::STATUS_CANCELLED,
            ]),
            ],
            'gateway_refund_id' => ['nullable', 'string', 'max:255'],
            'gateway_response' => ['nullable', 'array'],
            'refunded_at' => ['nullable', 'date'],
            'failed_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'refund_number' => 'Refund Number',
            'payment_id' => 'Payment',
            'invoice_id' => 'Invoice',
            'customer_id' => 'Customer',
            'refunded_at' => 'Refunded At',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new RefundDefinition;
    }

    protected function getModelClass(): string
    {
        return Refund::class;
    }
}
