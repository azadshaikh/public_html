<?php

namespace Modules\Subscriptions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Customers\Models\Customer;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'plan_id' => Plan::factory(),
            'status' => Subscription::STATUS_ACTIVE,
            'price' => $this->faker->randomFloat(2, 4.99, 999.99),
            'currency' => 'USD',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ];
    }
}
