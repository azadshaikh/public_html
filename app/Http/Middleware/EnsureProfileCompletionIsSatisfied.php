<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompletionIsSatisfied
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        if (blank($user->first_name)) {
            return to_route('profile.complete');
        }

        return $next($request);
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->routeIs('profile.complete') || $request->routeIs('profile.complete.store')) {
            return true;
        }

        if ($request->routeIs('logout')) {
            return true;
        }

        if (setting('registration_require_email_verification', true) && ! $request->user()?->hasVerifiedEmail()) {
            return true;
        }

        if ($request->routeIs('verification.notice')) {
            return true;
        }

        if ($request->routeIs('verification.verify')) {
            return true;
        }

        return $request->routeIs('verification.send');
    }
}
