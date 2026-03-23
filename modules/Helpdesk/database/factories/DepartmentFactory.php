<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\Department;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Support', 'Sales', 'Billing', 'Technical', 'Feedback',
                'Engineering', 'Marketing', 'Operations', 'HR', 'Legal',
            ]),
            'description' => $this->faker->optional()->sentence(),
            'department_head' => User::factory(),
            'visibility' => 'public',
            'status' => 'active',
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }
}
