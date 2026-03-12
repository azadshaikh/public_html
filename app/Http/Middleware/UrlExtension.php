<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UrlExtension
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        $adminSlug = trim((string) config('app.admin_slug'), '/');

        // Exclude backend/admin and API routes.
        if ($adminSlug !== '' && ($path === $adminSlug || str_starts_with($path, $adminSlug.'/'))) {
            return $next($request);
        }

        if ($path === 'api' || str_starts_with($path, 'api/')) {
            return $next($request);
        }

        $extension = setting('seo_url_extension', '');
        if (empty($extension)) {
            return $next($request);
        }

        // Don't apply to root
        if ($path === '') {
            return $next($request);
        }

        $newPath = '/'.$path;
        if (! str_ends_with($newPath, (string) $extension)) {
            // Preserve query string
            $query = $request->getQueryString();
            $url = url($newPath).$extension.($query ? '?'.$query : '');

            return redirect($url, 301);
        }

        return $next($request);
    }
}
