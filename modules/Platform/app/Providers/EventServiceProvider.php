<?php

namespace Modules\Platform\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Platform\Events\WebsiteCreatedEvent;
use Modules\Platform\Events\WebsiteDeletedEvent;
use Modules\Platform\Listeners\WebsiteCreated as WebsiteCreatedListener;
use Modules\Platform\Listeners\WebsiteDeleted as WebsiteDeletedListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        WebsiteCreatedEvent::class => [
            WebsiteCreatedListener::class,
        ],
        WebsiteDeletedEvent::class => [
            WebsiteDeletedListener::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
