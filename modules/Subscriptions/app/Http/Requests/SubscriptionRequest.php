<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Closure;
use Illuminate\Validation\Rule;
use Modules\Subscriptions\Definitions\SubscriptionDefinition;
use Modules\Subscriptions\Models\PlanPrice;
use Modules\Subscriptions\Models\Subscription;

class SubscriptionRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $rules = [
            'plan_id' => [
                'required',
                'integer',
                'exists:subscriptions_plans,id',
            ],
            'plan_price_id' => [
                $this->isUpdate() ? 'nullable' : 'required',
                'integer',
                Rule::exists('subscriptions_plan_prices', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $planId = (int) $this->input('plan_id');
                    if ($planId <= 0) {
                        return;
                    }

                    $isValidPrice = PlanPrice::query()
                        ->where('id', (int) $value)
                        ->where('plan_id', $planId)
                        ->where('is_active', true)
                        ->exists();

                    if (! $isValidPrice) {
                        $fail('Please select a valid active billing option for the selected plan.');
                    }
                },
            ],
            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'trial_days' => [
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],
        ];

        // For create, we need customer info
        if (! $this->isUpdate()) {
            $rules['customer_id'] = ['required', 'integer', 'exists:customers_customers,id'];
        }

        // For update, allow status changes
        if ($this->isUpdate()) {
            $rules['status'] = [
                'nullable',
                'string',
                'in:'.implode(',', [
                    Subscription::STATUS_ACTIVE,
                    Subscription::STATUS_TRIALING,
                    Subscription::STATUS_PAST_DUE,
                    Subscription::STATUS_CANCELED,
                    Subscription::STATUS_EXPIRED,
                    Subscription::STATUS_PAUSED,
                ]),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Please select a subscription plan.',
            'plan_id.exists' => 'The selected plan does not exist.',
            'plan_price_id.required' => 'Please select a billing option.',
            'plan_price_id.exists' => 'The selected billing option does not exist.',
            'customer_id.required' => 'Please select a customer.',
            'price.min' => 'Price cannot be negative.',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new SubscriptionDefinition;
    }
}
