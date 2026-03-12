<?php

namespace App\Http\Middleware;

use App\Services\SiteAccessProtectionService;
use Closure;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SiteAccessProtectionMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The site access protection service instance.
         */
        protected SiteAccessProtectionService $siteAccessProtectionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):((\Illuminate\Http\Response|RedirectResponse))  $next
     * @return \Illuminate\Http\Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Check if site access protection is enabled
            if ($this->siteAccessProtectionService->isSiteAccessProtectionEnabled()) {
                // Skip site access protection for excluded routes
                $isExcludedRoute = $this->isExcludedRoute($request);

                // Check if access is already verified in session
                $isAccessVerified = $this->siteAccessProtectionService->isSiteAccessVerified();

                if (! $isExcludedRoute && ! $isAccessVerified) {
                    // Store the intended URL for redirect after access verification
                    $this->siteAccessProtectionService->storeIntendedUrl($request->fullUrl());

                    return to_route('site.access.protection.form');
                }
            }

            return $next($request);
        } catch (Exception $exception) {
            // In case of errors, fail safely by allowing the request through
            Log::error('Site access protection middleware error: '.$exception->getMessage());

            return $next($request);
        }
    }

    /**
     * Check if the current route should be excluded from site access protection.
     */
    private function isExcludedRoute(Request $request): bool
    {
        $adminSlug = trim((string) config('app.admin_slug'), '/');

        // Exclude login and admin routes
        $excludedPatterns = [
            'login*',
            'site-access-protection*', // Include site access protection routes themselves
        ];

        if ($adminSlug !== '') {
            $excludedPatterns[] = $adminSlug;
            $excludedPatterns[] = $adminSlug.'/*';
        }

        foreach ($excludedPatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
