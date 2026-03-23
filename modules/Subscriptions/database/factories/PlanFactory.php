<?php

namespace Modules\Subscriptions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPrice;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'trial_days' => 7,
            'grace_days' => 3,
            'sort_order' => 0,
            'is_popular' => false,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function ($plan): void {
            /** @var Plan $plan */
            PlanPrice::factory()->for($plan)->create([
                'billing_cycle' => Plan::CYCLE_MONTHLY,
                'sort_order' => 0,
            ]);
        });
    }
}
