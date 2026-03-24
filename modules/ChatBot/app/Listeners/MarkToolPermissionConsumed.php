<?php

declare(strict_types=1);

namespace Modules\ChatBot\Listeners;

use Laravel\Ai\Events\ToolInvoked;
use Modules\ChatBot\Services\ToolPermissionService;

class MarkToolPermissionConsumed
{
    public function __construct(private readonly ToolPermissionService $permissions) {}

    public function handle(ToolInvoked $event): void
    {
        $this->permissions->markInvocationCompleted($event);
    }
}
