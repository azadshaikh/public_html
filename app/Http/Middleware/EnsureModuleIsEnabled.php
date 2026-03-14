<?php

namespace App\Http\Middleware;

use App\Modules\ModuleManager;
use App\Support\Auth\SuperUserAccess;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleIsEnabled
{
    public function __construct(
        protected ModuleManager $moduleManager,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response|RedirectResponse
    {
        if (! $this->moduleManager->isEnabled($module)) {
            $route = SuperUserAccess::allows($request->user())
                ? 'app.masters.modules.index'
                : 'dashboard';

            return to_route($route)->with('error', 'That module is disabled.');
        }

        return $next($request);
    }
}
