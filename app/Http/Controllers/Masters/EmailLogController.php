<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Scaffold\ScaffoldController;
use App\Services\EmailLogService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmailLogController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly EmailLogService $emailLogService
    ) {}

    // ================================================================
    // MIDDLEWARE (Permission control)
    // ================================================================

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_email_logs', only: ['index', 'show', 'data']),
        ];
    }

    // ================================================================
    // REQUIRED: Return the service
    // ================================================================

    protected function service(): EmailLogService
    {
        return $this->emailLogService;
    }
}
