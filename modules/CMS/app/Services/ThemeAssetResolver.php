<?php

namespace Modules\CMS\Services;

use Modules\CMS\Models\Theme;

/**
 * Resolves theme assets with child theme fallback support.
 * Uses request-level caching to minimize file system checks.
 */
class ThemeAssetResolver
{
    /**
     * Request-level cache for resolved asset paths
     * Key: "themeDir:assetPath" => resolved theme directory or null
     */
    private static array $assetCache = [];

    /**
     * Cached theme hierarchy (child -> parent chain)
     */
    private static ?array $themeHierarchy = null;

    /**
     * Current active theme directory
     */
    private static ?string $activeThemeDir = null;

    /**
     * Resolve an asset path across the theme hierarchy.
     * Returns the theme directory where the asset exists, or null if not found.
     *
     * @param  string  $path  Asset path relative to theme's assets folder (e.g., 'css/style.css')
     * @return string|null The theme directory containing the asset, or null if not found
     */
    public static function resolve(string $path): ?string
    {
        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return null;
        }

        $themeDir = $activeTheme['directory'];

        // Check if cache needs invalidation (theme changed)
        if (self::$activeThemeDir !== $themeDir) {
            self::invalidate();
            self::$activeThemeDir = $themeDir;
        }

        $cacheKey = $themeDir.':'.$path;

        // Return cached result if available
        if (array_key_exists($cacheKey, self::$assetCache)) {
            return self::$assetCache[$cacheKey];
        }

        // Get theme hierarchy (cached for request lifetime)
        $hierarchy = self::getThemeHierarchy($themeDir);
        $themesPath = Theme::getThemesPath();

        // Check each theme in hierarchy for the asset
        foreach ($hierarchy as $themeDirToCheck) {
            $assetPath = $themesPath.'/'.$themeDirToCheck.'/assets/'.$path;
            if (file_exists($assetPath)) {
                self::$assetCache[$cacheKey] = $themeDirToCheck;

                return $themeDirToCheck;
            }
        }

        // Asset not found in any theme
        self::$assetCache[$cacheKey] = null;

        return null;
    }

    /**
     * Get the full asset path for versioning purposes.
     *
     * @param  string  $path  Asset path relative to theme's assets folder
     * @return string|null Full filesystem path to the asset, or null if not found
     */
    public static function getFullPath(string $path): ?string
    {
        $themeDir = self::resolve($path);

        if (! $themeDir) {
            return null;
        }

        return Theme::getThemesPath().'/'.$themeDir.'/assets/'.$path;
    }

    /**
     * Invalidate the asset cache.
     * Should be called when:
     * - Theme is changed/activated
     * - Theme files are modified
     * - Child theme is created/deleted
     */
    public static function invalidate(): void
    {
        self::$assetCache = [];
        self::$themeHierarchy = null;
        self::$activeThemeDir = null;
    }

    /**
     * Get cache statistics (for debugging).
     */
    public static function getCacheStats(): array
    {
        return [
            'cached_assets' => count(self::$assetCache),
            'active_theme' => self::$activeThemeDir,
            'hierarchy' => self::$themeHierarchy,
        ];
    }

    /**
     * Get theme hierarchy with caching.
     */
    private static function getThemeHierarchy(string $themeDir): array
    {
        if (self::$themeHierarchy === null) {
            self::$themeHierarchy = Theme::getThemeHierarchy($themeDir);
        }

        return self::$themeHierarchy;
    }
}
