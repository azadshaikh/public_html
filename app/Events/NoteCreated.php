<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Note;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a note is created.
 *
 * @example
 * // Listen to this event in EventServiceProvider
 * NoteCreated::class => [
 *     NotifyOnTicketNote::class,
 * ]
 */
class NoteCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Note $note
    ) {}
}
