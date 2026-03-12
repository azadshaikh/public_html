<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Note;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a note is pinned or unpinned.
 */
class NotePinned
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Note  $note  The note that was pinned/unpinned
     * @param  bool  $isPinned  True if pinned, false if unpinned
     */
    public function __construct(
        public Note $note,
        public bool $isPinned
    ) {}
}
