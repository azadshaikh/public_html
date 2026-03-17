<?php

namespace Modules\Platform\Listeners;

use Modules\Platform\Events\WebsiteDeletedEvent as EventsWebsiteDeleted;

class WebsiteDeleted
{
    /**
     * Handle the event.
     *
     * Note: User and domain counts are now fetched directly from Hestia
     * during server sync, not maintained incrementally.
     */
    public function handle(EventsWebsiteDeleted $event): void
    {
        // Server user/domain counts are now synced from Hestia directly
        // via a-get-server-info script during server sync operations.
        // No incremental updates needed here.
    }
}
