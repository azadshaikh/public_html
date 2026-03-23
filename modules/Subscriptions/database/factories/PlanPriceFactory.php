<?php

namespace Modules\Subscriptions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPrice;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    public function definition(): array
    {
        return [
            'billing_cycle' => $this->faker->randomElement([
                Plan::CYCLE_MONTHLY,
                Plan::CYCLE_YEARLY,
            ]),
            'price' => $this->faker->randomFloat(2, 4.99, 999.99),
            'currency' => 'USD',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
