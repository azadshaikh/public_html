<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;

class NavigationAggregator
{
    /**
     * Build unified sidebar navigation grouped by area.
     * - Caches the base structure (without active states) per user and module config versions
     * - Applies active states after cache retrieval
     * - Returns sections grouped by area and sorted by weight
     */
    public static function getUnifiedByArea($user = null): array
    {
        $user ??= auth()->user();
        $cacheKey = NavigationHelper::generateSidebarCacheKey($user);
        $ttl = (int) config('navigation.cache_ttl', 21600);
        $cacheEnabled = (bool) config('navigation.cache_enabled', true);

        // Build or read the BASE structure (no active flags)
        if ($cacheEnabled) {
            $allSections = cache()->remember($cacheKey, $ttl, fn (): array => self::buildBaseSections($user));
        } else {
            $allSections = self::buildBaseSections($user);
        }

        // Apply active states AFTER cache retrieval (recursively)
        $currentRoute = request()->route();
        $currentName = $currentRoute ? $currentRoute->getName() : null;
        $currentParams = $currentRoute ? $currentRoute->parameters() : [];
        foreach ($allSections as &$section) {
            self::applyActiveFlags($section['items'], $currentName, $currentParams);
        }

        unset($section);

        // Group by area and sort within each area by weight
        $byArea = ['top' => [], 'cms' => [], 'modules' => [], 'bottom' => []];
        foreach ($allSections as $sec) {
            $area = $sec['area'] ?? 'bottom';
            if (! isset($byArea[$area])) {
                $byArea[$area] = [];
            }

            $byArea[$area][] = $sec;
        }

        foreach ($byArea as &$list) {
            usort($list, fn (array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
        }

        unset($list);

        return $byArea;
    }

    /**
     * Recursively apply active flags to items. Returns true if any descendant is active.
     *
     * Accepts precomputed current route name and params to avoid repeated
     * request() calls while scanning a large navigation tree.
     */
    private static function applyActiveFlags(array &$items, ?string $currentName = null, array $currentParams = []): bool
    {
        $anyActive = false;

        // Resolve current route name/params if not provided
        if ($currentName === null || $currentParams === []) {
            $currentRoute = request()->route();
            if ($currentName === null) {
                $currentName = $currentRoute ? $currentRoute->getName() : null;
            }

            if ($currentParams === []) {
                $currentParams = $currentRoute ? $currentRoute->parameters() : [];
            }
        }

        foreach ($items as &$item) {
            $descActive = false;
            if (! empty($item['children'])) {
                $descActive = self::applyActiveFlags($item['children'], $currentName, $currentParams);
            }

            $selfActive = NavigationHelper::isActive($item['active_patterns'] ?? [], $currentName, $currentParams);
            $item['active'] = $selfActive || $descActive;

            if ($item['active']) {
                $anyActive = true;
            }
        }

        unset($item);

        return $anyActive;
    }

    /**
     * Build the base sections array from application and module navigation configs.
     *
     * Behavior
     * - Applies permission filtering per item
     * - Safely resolves URLs for named routes (leaves '#' if route missing)
     * - Resolves static badges only (badge.type === 'static')
     * - Does NOT include 'active' flags so the cached structure is route-agnostic
     *
     * Inputs
     * - $user: optional user to evaluate permissions for
     * - module navigation files are included via PHP and expected to return arrays
     *
     * Returns
     * - array of sections ready for rendering and further active-state processing
     */
    private static function buildBaseSections($user = null): array
    {
        $user ??= auth()->user();
        $allSections = [];

        // Main application navigation
        $navigationConfig = config('navigation.sections', []);
        // Dynamic badge functions removed; keep placeholder for compatibility
        $badgeFunctions = [];

        foreach ($navigationConfig as $sectionKey => $section) {
            if (! isset($section['items'])) {
                continue;
            }

            if (! is_array($section['items'])) {
                continue;
            }

            if (! self::passesAccessFilters($section, $user)) {
                continue;
            }

            $sectionItems = self::buildItems($section['items'], $user, $badgeFunctions);

            if ($sectionItems !== []) {
                $allSections[] = [
                    'key' => $sectionKey,
                    'label' => __($section['label'] ?? ''),
                    'weight' => $section['weight'] ?? 0,
                    'type' => 'app',
                    'area' => $section['area'] ?? 'bottom',
                    'show_label' => $section['show_label'] ?? true,
                    'items' => $sectionItems,
                ];
            }
        }

        // Module navigation from modules/*/config/navigation.php
        // Supports both single 'section' (legacy) and multiple 'sections' arrays returned by modules.
        if (function_exists('active_modules')) {
            foreach (active_modules() as $module) {
                $moduleSlug = $module['slug'];
                $moduleConfigPath = base_path(sprintf('modules/%s/config/navigation.php', $module['folder_name']));

                if (! file_exists($moduleConfigPath)) {
                    continue;
                }

                try {
                    $moduleConfig = include $moduleConfigPath;
                    $moduleBadgeFunctions = $moduleConfig['badge_functions'] ?? [];

                    // Collect one or more sections from the module config
                    $sectionsToLoad = [];
                    if (! empty($moduleConfig['sections']) && is_array($moduleConfig['sections'])) {
                        $sectionsToLoad = $moduleConfig['sections'];
                    } elseif (! empty($moduleConfig['section']) && is_array($moduleConfig['section'])) {
                        // keep legacy single section support
                        $sectionsToLoad = [$moduleConfig['section']];
                    }

                    foreach ($sectionsToLoad as $secKey => $moduleSection) {
                        if (! isset($moduleSection['items'])) {
                            continue;
                        }

                        if (! is_array($moduleSection['items'])) {
                            continue;
                        }

                        if (! self::passesAccessFilters($moduleSection, $user)) {
                            continue;
                        }

                        $moduleItems = self::buildItems($moduleSection['items'], $user, $moduleBadgeFunctions);

                        if ($moduleItems !== []) {
                            $allSections[] = [
                                'key' => is_string($secKey) ? $secKey : ($moduleSection['key'] ?? $moduleSlug),
                                'label' => __($moduleSection['label'] ?? ucfirst((string) $moduleSlug)),
                                'weight' => $moduleSection['weight'] ?? 1000,
                                'type' => 'module',
                                'area' => $moduleSection['area'] ?? 'modules',
                                'show_label' => $moduleSection['show_label'] ?? true,
                                'items' => $moduleItems,
                            ];
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to load module navigation', [
                        'module' => $moduleSlug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $allSections;
    }

    /**
     * Recursively build items with permissions, urls, badges and children.
     *
     * Filtering is applied per item in this order:
     *  1. Access filters (permission + role) via passesAccessFilters() — supports string or array values.
     *     NOTE: There is no separate NavigationHelper::hasPermission() wrapper; filtering runs
     *     inline through passesAccessFilters() → passesPermissionFilter() / passesRoleFilter().
     *  2. Module check — if an item has a 'module' key, module_enabled() is called to hide the
     *     item when that module is disabled. This is NOT delegated to a NavigationHelper::hasModule()
     *     method; it is intentionally implemented inline here.
     *
     * Only static badges are supported now. If an item contains a badge with
     * 'type' => 'static' the 'value' will be returned as-is. Any 'count'
     * or dynamic badge types are ignored.
     */
    private static function buildItems(array $items, $user, array $badgeFunctions): array
    {
        $built = [];

        foreach ($items as $itemKey => $item) {
            if (! self::passesAccessFilters($item, $user)) {
                continue;
            }

            // Module check for item - if module is specified and not active, hide the item
            if (isset($item['module']) && $item['module']) {
                $moduleSlug = strtolower((string) $item['module']);
                if (function_exists('module_enabled')) {
                    if (! module_enabled($moduleSlug)) {
                        continue;
                    }
                } elseif (function_exists('active_modules')) {
                    if (! active_modules($moduleSlug)) {
                        continue;
                    }
                }
            }

            // Recursively process children first
            $children = [];
            if (isset($item['children']) && is_array($item['children'])) {
                $children = self::buildItems($item['children'], $user, $badgeFunctions);
            }

            // Build safe URL
            $itemUrl = '#';
            try {
                if (! empty($item['route'])) {
                    if (is_array($item['route'])) {
                        $itemUrl = route($item['route']['name'], $item['route']['params'] ?? []);
                    } else {
                        $itemUrl = route($item['route']);
                    }
                }
            } catch (Exception) {
                // Route may not exist; leave as '#'
            }

            if ($children === [] && $itemUrl === '#') {
                continue;
            }

            // Resolve badge value: support only static badges (type === 'static')
            $badgePayload = null;
            if (isset($item['badge']) && is_array($item['badge']) && (($item['badge']['type'] ?? null) === 'static')) {
                $badgePayload = [
                    'value' => $item['badge']['value'] ?? null,
                    'color' => $item['badge']['color'] ?? 'primary',
                ];
            }

            $built[] = [
                'key' => is_string($itemKey) ? $itemKey : ($item['key'] ?? ''),
                'label' => __($item['label'] ?? ''),
                'url' => $itemUrl,
                'icon' => $item['icon'] ?? '',
                'active_patterns' => $item['active_patterns'] ?? [],
                'badge' => $badgePayload,
                // Link behaviors
                'target' => $item['target'] ?? null,
                'hard_reload' => (bool) ($item['hard_reload'] ?? false),
                'attributes' => isset($item['attributes']) && is_array($item['attributes']) ? $item['attributes'] : [],
                'default_open' => (bool) ($item['default_open'] ?? false),
                'children' => $children,
                'hasChildren' => $children !== [],
            ];
        }

        return $built;
    }

    /**
     * Determine if a section/item passes permission + role filters.
     *
     * This is the single entry point for all access control checks on nav entries.
     * Both sections (in buildBaseSections) and individual items (in buildItems) run
     * through this method, so neither a separate NavigationHelper::hasPermission() nor
     * a hasModule() wrapper is needed — all filtering is centralised here and in
     * passesPermissionFilter() / passesRoleFilter() below.
     */
    private static function passesAccessFilters(array $entry, $user): bool
    {
        if (! self::passesPermissionFilter($entry['permission'] ?? null, $user)) {
            return false;
        }

        return self::passesRoleFilter($entry['role'] ?? null, $user);
    }

    /**
     * Permission filter supports string or array values.
     */
    private static function passesPermissionFilter(string|array|null $permission, $user): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if (is_string($permission)) {
            return $user->can($permission);
        }

        foreach ($permission as $permissionName) {
            if ($permissionName && $user->can($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Role filter supports string or array values.
     */
    private static function passesRoleFilter(string|array|null $roles, $user): bool
    {
        if ($roles === null || $roles === '') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if (is_string($roles)) {
            return $user->hasRole($roles);
        }

        foreach ($roles as $roleName) {
            if ($roleName && $user->hasRole($roleName)) {
                return true;
            }
        }

        return false;
    }
}
