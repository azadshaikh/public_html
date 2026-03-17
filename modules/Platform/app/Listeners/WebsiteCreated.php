<?php

namespace Modules\Platform\Listeners;

use Modules\Platform\Events\WebsiteCreatedEvent as EventsWebsiteCreated;

class WebsiteCreated
{
    /**
     * Handle the event.
     *
     * Note: User and domain counts are now fetched directly from Hestia
     * during server sync, not maintained incrementally.
     */
    public function handle(EventsWebsiteCreated $event): void
    {
        // Server user/domain counts are now synced from Hestia directly
        // via a-get-server-info script during server sync operations.
        // No incremental updates needed here.
    }
}
