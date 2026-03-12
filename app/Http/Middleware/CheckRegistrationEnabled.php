<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRegistrationEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('registration_enable_registration', true)) {
            $message = __('settings.registration_disabled_message');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], Response::HTTP_FORBIDDEN);
            }

            return to_route('login')
                ->withErrors(['registration' => $message]);
        }

        return $next($request);
    }
}
