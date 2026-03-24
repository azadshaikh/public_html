<?php

declare(strict_types=1);

namespace Modules\ChatBot\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;
use Modules\ChatBot\Listeners\EnforceToolPermission;
use Modules\ChatBot\Listeners\MarkToolPermissionConsumed;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        InvokingTool::class => [
            EnforceToolPermission::class,
        ],
        ToolInvoked::class => [
            MarkToolPermissionConsumed::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
