<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDN Cache Headers Middleware
 *
 * Sets Cache-Control headers for CDN and browser caching.
 * This is separate from Response Cache (server-side full-page caching).
 * Only applies to GET requests in production when CDN_CACHE_HEADERS is enabled.
 */
class CdnCacheHeadersMiddleware
{
    /**
     * Handle an incoming request and set appropriate cache headers.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldCacheResponse($request, $response)) {
            $maxAge = config('app.cdn_cache_max_age', 31536000); // 1 year default
            $response->headers->set('Cache-Control', 'public, max-age='.$maxAge.', s-maxage='.$maxAge);
        } else {
            $response->headers->set('Cache-Control', 'no-cache, private');
        }

        return $response;
    }

    /**
     * Determine if the response should be cached.
     */
    public function shouldCacheResponse($request, Response $response): bool
    {
        if (! config('app.cdn_cache_headers', false)) {
            return false;
        }

        if ($request->is('api/*') === true) {
            return false;
        }

        if ($request->method() !== 'GET') {
            return false;
        }

        if ($request->user() !== null) {
            return false;
        }

        if ($this->isAuthOrSessionRoute($request)) {
            return false;
        }

        if ($request->is('checkout/*') === true || $request->is('help/*') === true) {
            return false;
        }

        if ($request->is('sitemap.xml') === true) {
            return false;
        }

        if ($request->is('sitemaps/*') === true) {
            return false;
        }

        $adminSlug = trim((string) config('app.admin_slug'), '/');
        if ($adminSlug !== '' && $request->is($adminSlug.'/*') === true) {
            return false;
        }

        if ($response->headers->has('Set-Cookie')) {
            return false;
        }

        return $response->isSuccessful();
    }

    private function isAuthOrSessionRoute(Request $request): bool
    {
        if ($request->is('login') === true || $request->is('login/*') === true) {
            return true;
        }

        if ($request->is('register') === true || $request->is('register/*') === true) {
            return true;
        }

        if ($request->is('forgot-password') === true || $request->is('forgot-password/*') === true) {
            return true;
        }

        if ($request->is('reset-password') === true || $request->is('reset-password/*') === true) {
            return true;
        }

        if ($request->is('verify-email') === true || $request->is('verify-email/*') === true) {
            return true;
        }

        if ($request->is('confirm-password') === true || $request->is('confirm-password/*') === true) {
            return true;
        }

        if ($request->routeIs('login')) {
            return true;
        }

        if ($request->routeIs('login.*')) {
            return true;
        }

        if ($request->routeIs('register')) {
            return true;
        }

        if ($request->routeIs('register.*')) {
            return true;
        }

        if ($request->routeIs('password.*')) {
            return true;
        }

        if ($request->routeIs('verification.*')) {
            return true;
        }

        return $request->routeIs('logout');
    }
}
