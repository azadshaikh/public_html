<?php

namespace Modules\Platform\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Platform\Models\Server;

/**
 * An event that is dispatched when a new website has been successfully created.
 * This allows other parts of the system to listen for and react to website
 * creation, for example, to update server statistics.
 */
class WebsiteCreatedEvent
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
