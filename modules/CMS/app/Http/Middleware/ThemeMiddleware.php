<?php

namespace Modules\CMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Modules\CMS\Models\Theme;
use Symfony\Component\HttpFoundation\Response;

class ThemeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAdminRequest($request)) {
            return $next($request);
        }

        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return $next($request);
        }

        // Get active theme
        $activeTheme = Theme::getActiveTheme();

        if ($activeTheme) {
            // Add theme view path for Laravel's view system
            $themePath = $activeTheme['path'];
            View::addLocation($themePath);
            View::addNamespace('themes', $themePath);

            // Share theme data with all views
            View::share('theme', $activeTheme);

            // Make sure theme is loaded and setup
            theme_setup();

            // Share additional theme data
            View::share('theme_options', fn (string $key, $default = null): mixed => theme_get_option($key, $default));
        }

        return $next($request);
    }

    private function isAdminRequest(Request $request): bool
    {
        $adminSlug = trim((string) config('app.admin_slug'), '/');
        $path = trim($request->path(), '/');

        if ($adminSlug === '') {
            return false;
        }

        return $path === $adminSlug || str_starts_with($path, $adminSlug.'/');
    }
}
