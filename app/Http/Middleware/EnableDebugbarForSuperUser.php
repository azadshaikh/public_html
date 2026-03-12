<?php

namespace App\Http\Middleware;

use Closure;
use Fruitcake\LaravelDebugbar\Facades\Debugbar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnableDebugbarForSuperUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Respect global debugbar toggle and only enable for authenticated super users.
        $debugbarFacade = Debugbar::class;

        if (class_exists($debugbarFacade)) {
            $configuredEnabled = value(config('debugbar.enabled'));
            if ($configuredEnabled === null) {
                $configuredEnabled = (bool) config('app.debug');
            }

            if (! $configuredEnabled) {
                $debugbarFacade::disable();
            } elseif ($request->user()?->isSuperUser()) {
                $debugbarFacade::enable();
            } else {
                $debugbarFacade::disable();
            }
        }

        return $next($request);
    }
}
