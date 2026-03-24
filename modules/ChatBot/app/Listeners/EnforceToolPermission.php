<?php

declare(strict_types=1);

namespace Modules\ChatBot\Listeners;

use Laravel\Ai\Events\InvokingTool;
use Modules\ChatBot\Services\ToolPermissionService;

class EnforceToolPermission
{
    public function __construct(private readonly ToolPermissionService $permissions) {}

    public function handle(InvokingTool $event): void
    {
        $this->permissions->enforceFromInvocation($event);
    }
}
