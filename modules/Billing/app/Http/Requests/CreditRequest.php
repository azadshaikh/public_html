<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Definitions\CreditDefinition;
use Modules\Billing\Models\Credit;

class CreditRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'credit_number' => ['nullable', 'string', 'max:50', $this->uniqueRule('credit_number')],
            'reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['required', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'amount_used' => ['nullable', 'numeric', 'min:0'],
            'amount_remaining' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'type' => ['required', Rule::in([
                Credit::TYPE_CREDIT_NOTE,
                Credit::TYPE_REFUND_CREDIT,
                Credit::TYPE_PROMO_CREDIT,
                Credit::TYPE_GOODWILL,
            ]),
            ],
            'status' => ['required', Rule::in([
                Credit::STATUS_ACTIVE,
                Credit::STATUS_EXHAUSTED,
                Credit::STATUS_EXPIRED,
                Credit::STATUS_CANCELLED,
            ]),
            ],
            'expires_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'credit_number' => 'Credit Number',
            'customer_id' => 'Customer',
            'invoice_id' => 'Invoice',
            'amount_remaining' => 'Remaining Amount',
            'expires_at' => 'Expiry Date',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new CreditDefinition;
    }

    protected function getModelClass(): string
    {
        return Credit::class;
    }
}
