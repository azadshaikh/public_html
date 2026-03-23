<?php

namespace Modules\Customers\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Customers\Enums\AnnualRevenue;
use Modules\Customers\Enums\CustomerGroup;
use Modules\Customers\Enums\CustomerSource;
use Modules\Customers\Enums\CustomerTier;
use Modules\Customers\Enums\Industry;
use Modules\Customers\Enums\OrganizationSize;
use Modules\Customers\Models\Customer;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Customer::class;

    /**
     * US-oriented company names for realistic seeding.
     *
     * @var array<string>
     */
    protected array $usCompanyNames = [
        'Apex Digital Solutions',
        'Blue Ridge Technologies',
        'Cascade Marketing Group',
        'Evergreen Consulting LLC',
        'First Coast Industries',
        'Golden State Ventures',
        'Harbor View Partners',
        'Ironwood Manufacturing',
        'Keystone Financial Services',
        'Lakeside Development Co.',
        'Meridian Health Systems',
        'Northern Lights Media',
        'Oakwood Properties Inc.',
        'Pacific Coast Logistics',
        'Riverside Analytics',
        'Summit Peak Advisors',
        'Trailblazer Innovations',
        'United Metro Services',
        'Venture Point Capital',
        'Westfield Commercial Group',
        'Yellowstone Energy Corp.',
        'Zenith Software Solutions',
        'Brightstar Communications',
        'Clearwater Dynamics',
        'Eagle Rock Enterprises',
    ];

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['company', 'person']);
        $isCompany = $type === 'company';

        return [
            'type' => $type,
            'company_name' => $isCompany ? fake()->randomElement($this->usCompanyNames) : null,
            'contact_first_name' => fake()->firstName(),
            'contact_last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+1 (###) ###-####'),
            'phone_code' => '+1',
            'billing_email' => fake()->optional(0.7)->safeEmail(),
            'billing_phone' => fake()->optional(0.5)->numerify('+1 (###) ###-####'),
            'tax_id' => fake()->optional(0.6)->numerify('##-#######'),
            'website' => fake()->optional(0.8)->url(),
            'currency' => 'USD',
            'language' => 'en',
            'tier' => fake()->randomElement(CustomerTier::cases()),
            'source' => fake()->randomElement(CustomerSource::cases()),
            'industry' => $isCompany ? fake()->randomElement(Industry::cases()) : null,
            'customer_group' => fake()->optional(0.5)->randomElement(CustomerGroup::cases()),
            'org_size' => $isCompany ? fake()->randomElement(OrganizationSize::cases()) : null,
            'revenue' => $isCompany ? fake()->randomElement(AnnualRevenue::cases()) : null,
            'tags' => fake()->optional(0.6)->randomElements(['vip', 'priority', 'new', 'returning', 'enterprise'], random_int(1, 3)),
            'opt_in_marketing' => fake()->boolean(70),
            'do_not_call' => fake()->boolean(10),
            'do_not_email' => fake()->boolean(10),
            'description' => fake()->optional(0.4)->sentence(),
            'status' => 'active',
            'metadata' => [],
        ];
    }

    /**
     * State for company customers only.
     */
    public function company(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'company',
            'company_name' => fake()->randomElement($this->usCompanyNames),
            'industry' => fake()->randomElement(Industry::cases()),
            'org_size' => fake()->randomElement(OrganizationSize::cases()),
            'revenue' => fake()->randomElement(AnnualRevenue::cases()),
        ]);
    }

    /**
     * State for individual customers only.
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'person',
            'company_name' => null,
            'industry' => null,
            'org_size' => null,
            'revenue' => null,
        ]);
    }

    /**
     * State for inactive customers.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }
}
