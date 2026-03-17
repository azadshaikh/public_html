<?php

namespace Modules\Platform\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Platform\Models\Server;

class WebsiteDeletedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var Server
     */
    public $dataobj;

    /**
     * Create a new event instance.
     */
    public function __construct(Server $dataobj)
    {
        $this->dataobj = $dataobj;
    }

    /**
     * Get the channels the event should be broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
