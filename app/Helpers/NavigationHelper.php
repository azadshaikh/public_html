<?php

namespace App\Helpers;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NavigationHelper
{
    /**
     * Get sidebar navigation grouped by area
     */
    public static function getSidebarNavigationByArea(): array
    {
        $config = config('navigation.sections', []);
        $areas = ['top' => [], 'cms' => [], 'modules' => [], 'bottom' => []];

        foreach ($config as $key => $section) {
            $area = $section['area'] ?? 'bottom';
            if (! isset($areas[$area])) {
                $areas[$area] = [];
            }

            $areas[$area][$key] = $section;
        }

        return $areas;
    }

    /**
     * Check if navigation item is active based on patterns
     *
     * Accepts optional precomputed current route name and params to avoid repeated
     * request() lookups when scanning a large navigation tree.
     */
    public static function isActive(array $patterns = [], ?string $currentName = null, array $currentParams = []): bool
    {
        if ($patterns === []) {
            return false;
        }

        // Resolve current route name/params if not provided
        if ($currentName === null) {
            $currentRoute = request()->route();
            $currentName = $currentRoute ? $currentRoute->getName() : null;
        }

        if ($currentParams === []) {
            $currentParams = request()->route() ? request()->route()->parameters() : [];
        }

        foreach ($patterns as $pattern) {
            if (is_array($pattern)) {
                // Handle complex patterns with parameters
                if (self::matchesComplexPattern($pattern, $currentName, $currentParams)) {
                    return true;
                }
            } else {
                // Simple string pattern (supports wildcards)
                if ($currentName && Str::is($pattern, $currentName)) {
                    return true;
                }

                // Fallback to request()->routeIs for compatibility
                if (request()->routeIs($pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve URL from route configuration
     */
    public static function resolveUrl(array $routeConfig): string
    {
        if (isset($routeConfig['url'])) {
            return $routeConfig['url'];
        }

        if (isset($routeConfig['name'])) {
            $params = $routeConfig['params'] ?? [];

            try {
                return route($routeConfig['name'], $params);
            } catch (Exception $e) {
                // Log error and return fallback
                Log::warning('Failed to resolve navigation route', [
                    'route' => $routeConfig,
                    'error' => $e->getMessage(),
                ]);

                return '#';
            }
        }

        return '#';
    }

    /**
     * Check if user has required permissions
     */
    public static function hasPermission(array $permissions = []): bool
    {
        if ($permissions === []) {
            return true; // No permissions required
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // Check if user has any of the required permissions
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get active CSS class for navigation items
     */
    public static function getActiveClass(array $patterns = [], string $activeClass = 'active'): string
    {
        return self::isActive($patterns) ? $activeClass : '';
    }

    /**
     * Generate navigation item ID
     */
    public static function generateItemId(string $key, string $prefix = 'nav-item'): string
    {
        return $prefix.'-'.str_replace('.', '-', $key);
    }

    /**
     * Validate navigation item structure
     */
    public static function validateItem(array $item): array
    {
        $errors = [];

        if (! isset($item['key']) || empty($item['key'])) {
            $errors[] = 'Navigation item missing required key field';
        }

        if (! isset($item['type']) || ! in_array($item['type'], ['link', 'submenu', 'divider', 'header'])) {
            $errors[] = 'Navigation item has invalid or missing type';
        }

        if (! isset($item['label']) || empty($item['label'])) {
            $errors[] = 'Navigation item missing required label field';
        }

        if ($item['type'] === 'link' && ! isset($item['route']) && ! isset($item['url'])) {
            $errors[] = 'Link navigation item must have either route or url';
        }

        if ($item['type'] === 'submenu' && (! isset($item['children']) || ! is_array($item['children']))) {
            $errors[] = 'Submenu navigation item must have children array';
        }

        return $errors;
    }

    /**
     * Generate cache key for unified sidebar navigation
     */
    public static function generateSidebarCacheKey($user = null): string
    {
        $user ??= Auth::user();

        // Build cache key hash including main navigation config and all active modules
        $cacheComponents = [
            ($user ? $user->id : 'guest'),
            serialize($user ? $user->getAllPermissions()->pluck('name')->sort()->toArray() : []),
            filemtime(config_path('navigation.php')),
            'admin_slug_'.config('app.admin_slug'),
            'schema_quick_open_v1',
        ];

        $cachedRoutesPath = app()->getCachedRoutesPath();
        $cacheComponents[] = 'routes_cache_'.(file_exists($cachedRoutesPath) ? filemtime($cachedRoutesPath) : 'none');

        // Add module navigation config timestamps to cache key
        if (function_exists('active_modules')) {
            foreach (active_modules() as $module) {
                $moduleConfigPath = base_path(sprintf('modules/%s/config/navigation.php', $module['folder_name']));
                if (file_exists($moduleConfigPath)) {
                    $cacheComponents[] = $module['slug'].'_'.filemtime($moduleConfigPath);
                }
            }
        }

        return 'sidebar_navigation_'.md5(implode('_', $cacheComponents));
    }

    /**
     * Generate cache key for navigation (legacy method - maintained for backward compatibility)
     */
    public static function generateCacheKey($user = null): string
    {
        return self::generateSidebarCacheKey($user);
    }

    /**
     * Clear sidebar navigation cache for specific user
     */
    public static function clearUserCache($userId = null): bool
    {
        try {
            $user = $userId ? User::query()->find($userId) : Auth::user();
            if (! $user) {
                return false;
            }

            $cacheKey = self::generateSidebarCacheKey($user);

            // Forget unified key and any legacy suffixed variants
            $keysToForget = [
                $cacheKey,
                $cacheKey.'_agg_v1',
                $cacheKey.'_agg_v2',
            ];

            foreach ($keysToForget as $key) {
                Cache::forget($key);
            }

            return true;
        } catch (Exception $exception) {
            Log::warning('Failed to clear sidebar navigation cache', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clear all sidebar navigation cache entries
     */
    public static function clearAllCache(): int
    {
        try {
            $cleared = 0;

            // For cache drivers that support getKeys()
            if (method_exists(Cache::getStore(), 'getKeys')) {
                $cacheKeys = Cache::getStore()->getKeys();

                foreach ($cacheKeys as $key) {
                    if (str_starts_with((string) $key, 'sidebar_navigation_') || str_starts_with((string) $key, 'navigation_')) {
                        Cache::forget($key);
                        $cleared++;
                    }
                }
            } else {
                // For database/other cache drivers, clear using user-based approach
                $users = User::query()->limit(100)->get(); // Limit to avoid memory issues

                foreach ($users as $user) {
                    $cacheKey = self::generateSidebarCacheKey($user);
                    $keysToForget = [
                        $cacheKey,
                        $cacheKey.'_agg_v1',
                        $cacheKey.'_agg_v2',
                    ];

                    foreach ($keysToForget as $key) {
                        if (Cache::has($key)) {
                            Cache::forget($key);
                            $cleared++;
                        }
                    }
                }

                // Also clear guest cache
                $guestKey = self::generateSidebarCacheKey();
                foreach ([$guestKey, $guestKey.'_agg_v1', $guestKey.'_agg_v2'] as $key) {
                    if (Cache::has($key)) {
                        Cache::forget($key);
                        $cleared++;
                    }
                }
            }

            return $cleared;
        } catch (Exception $exception) {
            Log::warning('Failed to clear all sidebar navigation cache', [
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Match complex route patterns with parameters
     *
     * Accepts optional precomputed current route name and params to avoid repeated
     * request() lookups when scanning a large navigation tree.
     */
    private static function matchesComplexPattern(array $pattern, ?string $currentName = null, array $currentParams = []): bool
    {
        if (! isset($pattern['route'])) {
            return false;
        }

        $routeName = $pattern['route'];
        $params = $pattern['params'] ?? [];

        // Resolve current name/params if not provided
        if ($currentName === null) {
            $currentRoute = request()->route();
            $currentName = $currentRoute ? $currentRoute->getName() : null;
        }

        if ($currentParams === []) {
            $currentParams = request()->route() ? request()->route()->parameters() : [];
        }

        if ($routeName !== $currentName) {
            return false;
        }

        // Check if current route parameters match
        foreach ($params as $key => $value) {
            if (($currentParams[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
