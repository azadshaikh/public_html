<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketReplies;

/**
 * @extends Factory<TicketReplies>
 */
class TicketRepliesFactory extends Factory
{
    protected $model = TicketReplies::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'content' => $this->faker->paragraph(),
            'attachments' => null,
            'is_internal' => false,
            'reply_by' => User::factory(),
        ];
    }

    public function internal(): static
    {
        return $this->state(['is_internal' => true]);
    }
}
