<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ComingSoonMode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):((\Illuminate\Http\Response|RedirectResponse))  $next
     * @return \Illuminate\Http\Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $comingSoonEnabled = filter_var(setting('coming_soon_enabled', false), FILTER_VALIDATE_BOOLEAN);

            // Check if coming soon mode is enabled
            if ($comingSoonEnabled === true) {
                $adminSlug = trim((string) config('app.admin_slug'), '/');

                // Determine if request is for backend (admin area)
                $isBackendRequest = $adminSlug !== '' && ($request->is($adminSlug.'/*') || $request->is($adminSlug));

                // Coming soon only applies to frontend
                if (! $isBackendRequest) {
                    // Check if user is authenticated (bypass coming soon for logged-in users)
                    if (Auth::check()) {
                        // Allow authenticated users to view the frontend
                        return $next($request);
                    }

                    // Show coming soon page for unauthenticated users on frontend
                    return response()->view('errors.coming-soon', [
                        'comingSoonMessage' => setting('coming_soon_message', ''),
                    ], 503);
                }
            }

            return $next($request);
        } catch (Exception) {
            // In case of errors, fail safely by allowing the request through
            return $next($request);
        }
    }
}
