<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Modules\Subscriptions\Definitions\PlanDefinition;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanFeature;

class PlanRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                $this->uniqueRule('code', 'subscriptions_plans'),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'prices' => [
                'nullable',
                'array',
            ],
            'prices.*.id' => [
                'nullable',
                'integer',
            ],
            'prices.*.billing_cycle' => [
                'required_with:prices.*',
                'string',
                'distinct',
                'in:'.implode(',', [
                    Plan::CYCLE_MONTHLY,
                    Plan::CYCLE_QUARTERLY,
                    Plan::CYCLE_YEARLY,
                    Plan::CYCLE_LIFETIME,
                ]),
            ],
            'prices.*.price' => [
                'required_with:prices.*',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'prices.*.currency' => [
                'required_with:prices.*',
                'string',
                'size:3',
            ],
            'prices.*.is_active' => [
                'nullable',
                'boolean',
            ],
            'prices.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'trial_days' => [
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],
            'grace_days' => [
                'nullable',
                'integer',
                'min:0',
                'max:90',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_popular' => [
                'nullable',
                'boolean',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'features' => [
                'nullable',
                'array',
            ],
            'features.*.id' => [
                'nullable',
                'integer',
            ],
            'features.*.code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                'distinct',
            ],
            'features.*.name' => [
                'required',
                'string',
                'max:100',
            ],
            'features.*.description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'features.*.type' => [
                'required',
                'string',
                'in:'.implode(',', [
                    PlanFeature::TYPE_BOOLEAN,
                    PlanFeature::TYPE_LIMIT,
                    PlanFeature::TYPE_VALUE,
                    PlanFeature::TYPE_UNLIMITED,
                ]),
            ],
            'features.*.value' => [
                'nullable',
                'string',
                'max:255',
            ],
            'features.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'A unique plan code is required.',
            'code.alpha_dash' => 'Plan code may only contain letters, numbers, dashes, and underscores.',
            'code.unique' => 'This plan code is already in use.',
            'name.required' => 'Plan name is required.',
            'prices.*.billing_cycle.required_with' => 'Please select a billing cycle for each price.',
            'prices.*.billing_cycle.in' => 'Please select a valid billing cycle.',
            'prices.*.billing_cycle.distinct' => 'Each pricing row must use a different billing cycle.',
            'prices.*.price.required_with' => 'Price is required for each billing option.',
            'prices.*.price.min' => 'Price cannot be negative.',
            'prices.*.currency.required_with' => 'Please select a currency for each price.',
            'prices.*.currency.size' => 'Currency must be a 3-letter code.',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new PlanDefinition;
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('features');
        $hasFeaturePayload = $this->has('features_present');

        // Normalize prices
        $prices = $this->input('prices');
        $hasPricesPayload = $this->has('prices_present');

        if ($hasPricesPayload && ! is_array($prices)) {
            $prices = [];
        }

        if (is_array($prices)) {
            $prices = array_values(array_filter($prices, fn (mixed $p): bool => is_array($p) && (! empty($p['price']) || ! empty($p['billing_cycle']))));
        }

        if ($hasFeaturePayload && ! is_array($features)) {
            $features = [];
        }

        if (is_array($features)) {
            $features = array_values(array_filter($features, function (mixed $feature): bool {
                if (! is_array($feature)) {
                    return false;
                }

                foreach (['code', 'name', 'description', 'type', 'value'] as $key) {
                    if (! empty($feature[$key])) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $payload = [
            'is_popular' => $this->boolean('is_popular', false),
            'is_active' => $this->boolean('is_active', false),
            'trial_days' => $this->input('trial_days') ?: 0,
            'grace_days' => $this->input('grace_days') ?: 0,
            'sort_order' => $this->input('sort_order') ?: 0,
        ];

        if ($hasFeaturePayload || is_array($features)) {
            $payload['features'] = $features;
        }

        if ($hasPricesPayload || is_array($prices)) {
            $payload['prices'] = $prices;
        }

        $this->merge($payload);
    }
}
