<?php

declare(strict_types=1);

namespace Modules\Todos\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Todos\Models\Todo;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    protected $model = Todo::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'on_hold', 'cancelled']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'visibility' => $this->faker->randomElement(['private', 'public']),
            'is_starred' => $this->faker->boolean(20),
            'start_date' => null,
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'labels' => null,
            'assigned_to' => null,
            'metadata' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed', 'completed_at' => now()]);
    }
}
