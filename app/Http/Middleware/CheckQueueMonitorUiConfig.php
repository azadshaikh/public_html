<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckQueueMonitorUiConfig
{
    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();

        throw_unless($route instanceof Route, NotFoundHttpException::class, 'Not Found');

        $allowed = match ($route->getName()) {
            'app.masters.queue-monitor.index' => config('queue-monitor.ui.enabled'),
            'app.masters.queue-monitor.data' => config('queue-monitor.ui.enabled'),
            'app.masters.queue-monitor.workers' => config('queue-monitor.ui.enabled') && config('queue-monitor.workers.enabled', true),
            'app.masters.queue-monitor.retry' => config('queue-monitor.ui.enabled') && config('queue-monitor.ui.allow_retry'),
            'app.masters.queue-monitor.cancel' => config('queue-monitor.ui.enabled') && config('queue-monitor.ui.allow_cancel', true),
            'app.masters.queue-monitor.destroy' => config('queue-monitor.ui.enabled') && config('queue-monitor.ui.allow_deletion'),
            'app.masters.queue-monitor.bulk-action' => config('queue-monitor.ui.enabled'),
            default => false,
        };

        throw_unless($allowed, NotFoundHttpException::class, 'Not Found');

        return $next($request);
    }
}
