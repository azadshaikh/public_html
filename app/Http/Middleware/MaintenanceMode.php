<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
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
            $maintenanceMode = filter_var(setting('maintenance_mode_enabled', false), FILTER_VALIDATE_BOOLEAN);

            // Check if maintenance mode is enabled
            if ($maintenanceMode === true) {
                $maintenanceType = setting('maintenance_maintenance_mode_type', 'frontend'); // frontend, backend, or both
                $adminSlug = trim((string) config('app.admin_slug'), '/');

                // Determine if request is for backend (admin area)
                $isBackendRequest = $adminSlug !== '' && ($request->is($adminSlug.'/*') || $request->is($adminSlug));
                $isFrontendRequest = ! $isBackendRequest;

                // Check if user is super user (bypass backend maintenance)
                $isSuperUser = Auth::check() && Auth::user()->hasRole('super_user');

                // Determine if maintenance should be applied
                $shouldApplyMaintenance = false;

                if ($maintenanceType === 'both') {
                    // Both frontend and backend are in maintenance
                    if ($isBackendRequest && $isSuperUser) {
                        // Super users can access backend even in maintenance
                        $shouldApplyMaintenance = false;
                    } elseif ($isBackendRequest && ! Auth::check()) {
                        // Allow unauthenticated users to reach login page
                        // $adminSlug is guaranteed non-empty here ($isBackendRequest requires it)
                        $shouldApplyMaintenance = ! $request->is($adminSlug.'/login');
                    } else {
                        // Everyone else sees maintenance
                        $shouldApplyMaintenance = true;
                    }
                } elseif ($maintenanceType === 'backend') {
                    // Only backend is in maintenance - frontend works normally
                    if ($isBackendRequest) {
                        if ($isSuperUser) {
                            // Super users can access backend
                            $shouldApplyMaintenance = false;
                        } elseif (! Auth::check()) {
                            // Allow access to login page
                            // $adminSlug is guaranteed non-empty here ($isBackendRequest requires it)
                            $shouldApplyMaintenance = ! $request->is($adminSlug.'/login');
                        } else {
                            // Regular authenticated users cannot access backend
                            $shouldApplyMaintenance = true;
                        }
                    }

                    // Frontend requests pass through (no maintenance)
                } elseif ($maintenanceType === 'frontend') {
                    // Only frontend is in maintenance - backend works normally
                    if ($isFrontendRequest) {
                        $shouldApplyMaintenance = true;
                    }

                    // Backend requests pass through (no maintenance)
                }

                if ($shouldApplyMaintenance) {
                    return response()->view('errors.maintenance', [
                        'maintenanceMessage' => setting('maintenance_message', ''),
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
