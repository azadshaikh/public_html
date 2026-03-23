<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\Department;
use Modules\Helpdesk\Models\Ticket;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ticket_number' => 'TK'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'department_id' => Department::factory(),
            'user_id' => User::factory(),
            'subject' => $this->faker->sentence(6),
            'description' => $this->faker->paragraphs(2, true),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'assigned_to' => null,
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
            'closed_by' => null,
            'attachments' => null,
            'metadata' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => 'resolved',
            'closed_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(['priority' => 'high']);
    }

    public function critical(): static
    {
        return $this->state(['priority' => 'critical']);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['assigned_to' => $user->id]);
    }
}
