<?php

namespace Modules\Customers\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerContact;

/**
 * @extends Factory<CustomerContact>
 */
class CustomerContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CustomerContact::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'phone_code' => '+1',
            'position' => fake()->jobTitle(),
            'is_primary' => false,
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
