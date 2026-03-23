<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Definitions\CouponDefinition;
use Modules\Billing\Models\Coupon;

class CouponRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $maxValue = $this->input('type') === Coupon::TYPE_PERCENT ? 100 : 999999.99;

        return [
            'code' => ['required', 'string', 'max:50', $this->uniqueRule('code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'min:0.01', 'max:'.$maxValue],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount_duration' => ['required', Rule::in([
                Coupon::DURATION_ONCE,
                Coupon::DURATION_REPEATING,
                Coupon::DURATION_FOREVER,
            ])],
            'duration_in_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['required', 'integer', 'min:1', 'max:255'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'applicable_plan_ids' => ['nullable', 'array'],
            'applicable_plan_ids.*' => ['integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'is_active' => ['boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Coupon Code',
            'name' => 'Coupon Name',
            'type' => 'Discount Type',
            'value' => 'Discount Value',
            'currency' => 'Currency',
            'discount_duration' => 'Discount Duration',
            'duration_in_months' => 'Duration (months)',
            'max_uses' => 'Max Uses',
            'max_uses_per_customer' => 'Max Uses Per Customer',
            'min_order_amount' => 'Minimum Order Amount',
            'applicable_plan_ids' => 'Applicable Plans',
            'expires_at' => 'Expiry Date',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This coupon code is already in use.',
            'expires_at.after' => 'The expiry date must be in the future.',
            'expires_at.after_or_equal' => 'The expiry date must not be in the past.',
            'value.max' => $this->input('type') === Coupon::TYPE_PERCENT
                ? 'Percentage value cannot exceed 100%.'
                : 'The discount value is too large.',
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }

        $this->prepareBooleanField('is_active');

        if (! $this->has('is_active')) {
            $this->merge(['is_active' => false]);
        }

        if ($this->has('applicable_plan_ids') && is_array($this->input('applicable_plan_ids'))) {
            $this->merge([
                'applicable_plan_ids' => array_map(intval(...), $this->input('applicable_plan_ids')),
            ]);
        }
    }

    protected function definition(): ScaffoldDefinition
    {
        return new CouponDefinition;
    }

    protected function getModelClass(): string
    {
        return Coupon::class;
    }
}
