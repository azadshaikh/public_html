<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\CMS\Events\ThemeActivated;
use Modules\CMS\Events\ThemeDeactivated;
use Modules\CMS\Services\ThemeConfigService;
use RuntimeException;
use Throwable;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'directory',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Static property to track if theme is already loaded in current request
     */
    private static bool $themeLoaded = false;

    /**
     * Check if theme is already loaded in current request
     */
    public static function isThemeLoaded(): bool
    {
        return self::$themeLoaded;
    }

    /**
     * Mark theme as loaded in current request
     */
    public static function markThemeLoaded(): void
    {
        self::$themeLoaded = true;
    }

    /**
     * Reset theme loaded state (useful for testing)
     */
    public static function resetThemeLoadedState(): void
    {
        self::$themeLoaded = false;
    }

    /**
     * Get the themes directory path
     */
    public static function getThemesPath(): string
    {
        return base_path('themes');
    }

    /**
     * Get all available file-based themes
     */
    public static function getAllThemes(): array
    {
        $themesPath = self::getThemesPath();

        if (! File::exists($themesPath)) {
            File::makeDirectory($themesPath, 0755, true);

            return [];
        }

        $themes = [];
        $directories = File::directories($themesPath);

        foreach ($directories as $directory) {
            $themeDir = basename((string) $directory);
            $themeInfo = self::getThemeInfo($themeDir);

            if ($themeInfo) {
                $themes[] = $themeInfo;
            }
        }

        return $themes;
    }

    /**
     * Get theme information from manifest.json
     */
    public static function getThemeInfo(string $themeDirectory): ?array
    {
        $themePath = self::getThemesPath().'/'.$themeDirectory;
        $manifestPath = $themePath.'/manifest.json';

        // Only use manifest.json for theme metadata
        if (! File::exists($manifestPath)) {
            return null;
        }

        $manifestContent = File::get($manifestPath);
        $themeInfo = json_decode($manifestContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Flatten nested structures for view compatibility
        $themeInfo = self::flattenManifestData($themeInfo);

        // Add additional information
        $themeInfo['directory'] = $themeDirectory;
        $themeInfo['path'] = $themePath;
        $themeInfo['screenshot'] = self::getThemeScreenshotFromManifest($themeInfo, $themeDirectory);
        $themeInfo['templates'] = self::getThemeTemplates($themeDirectory);
        $themeInfo['files'] = self::getThemeFiles($themeDirectory);
        $themeInfo['is_active'] = self::isActiveTheme($themeDirectory);

        return $themeInfo;
    }

    /**
     * Activate a theme
     */
    public static function activateTheme(string $themeDirectory): bool
    {
        $themeInfo = self::getThemeInfo($themeDirectory);

        if (! $themeInfo) {
            return false;
        }

        // Store current theme for event
        $previousTheme = self::getActiveTheme();

        // Fire theme deactivation event if switching themes
        if ($previousTheme && $previousTheme['directory'] !== $themeDirectory) {
            event(new ThemeDeactivated($previousTheme, $themeInfo));
        }

        // Write active theme to file
        $activeThemeFile = self::getThemesPath().'/.active_theme';
        File::put($activeThemeFile, $themeDirectory);

        // Clear cache with proper namespace prefix to prevent collisions
        Cache::forget('app_theme_templates_v1_'.$themeDirectory);

        // Load and setup the theme
        self::loadTheme($themeDirectory);

        // Fire theme activation event
        event(new ThemeActivated($themeInfo, $previousTheme));

        return true;
    }

    /**
     * Get the active theme
     */
    public static function getActiveTheme(): ?array
    {
        $overrideTheme = config('theme.active');
        if (! empty($overrideTheme)) {
            return self::getThemeInfo($overrideTheme);
        }

        static $theme = null;
        static $checked = false;
        if ($checked) {
            return $theme;
        }

        $activeThemeFile = self::getThemesPath().'/.active_theme';

        if (! File::exists($activeThemeFile)) {
            $theme = null;
        } else {
            $activeDir = trim(File::get($activeThemeFile));
            $theme = $activeDir !== '' && $activeDir !== '0' ? self::getThemeInfo($activeDir) : null;
        }

        $checked = true;

        return $theme;
    }

    /**
     * Get the active theme and ensure it's loaded
     */
    public static function getActiveThemeLoaded(): ?array
    {
        $theme = self::getActiveTheme();

        if ($theme && ! self::isThemeLoaded()) {
            self::loadTheme($theme['directory']);
        }

        return $theme;
    }

    /**
     * Load theme functions and setup
     */
    /**
     * Load theme configuration and setup (secure, no PHP execution)
     */
    public static function loadTheme(string $themeDirectory): void
    {
        // Prevent loading the same theme multiple times in current request
        if (self::isThemeLoaded()) {
            return;
        }

        try {
            // Use secure configuration service instead of functions.php
            $configService = resolve(ThemeConfigService::class);
            $configService->setupTheme($themeDirectory);

            // Mark theme as loaded for current request
            self::markThemeLoaded();
        } catch (Throwable $throwable) {
            // Log the error and prevent theme loading
            Log::error('Error loading theme configuration: '.$themeDirectory, [
                'error' => $throwable->getMessage(),
            ]);
            throw new RuntimeException('Failed to load theme configuration safely: '.$themeDirectory, $throwable->getCode(), $throwable);
        }
    }

    /**
     * Get template hierarchy for a given context
     */
    public static function getTemplateHierarchy(string $context, array $params = []): array
    {
        $hierarchy = [];

        // If a custom template is specified, add it first (highest priority)
        if (isset($params['template']) && $params['template']) {
            $hierarchy[] = sprintf('%s-%s.twig', $context, $params['template']);
        }

        switch ($context) {
            case 'post':
            case 'single': // Backward compatibility
                if (isset($params['id'])) {
                    $hierarchy[] = sprintf('post-%s.twig', $params['id']);
                    $hierarchy[] = sprintf('single-%s.twig', $params['id']); // Backward compatibility
                }

                if (isset($params['slug'])) {
                    $hierarchy[] = sprintf('post-%s.twig', $params['slug']);
                    $hierarchy[] = sprintf('single-%s.twig', $params['slug']); // Backward compatibility
                }

                $hierarchy[] = 'post.twig';
                $hierarchy[] = 'single.twig'; // Backward compatibility
                break;
            case 'author':
                if (isset($params['slug'])) {
                    $hierarchy[] = sprintf('author-%s.twig', $params['slug']);
                }

                if (isset($params['id'])) {
                    $hierarchy[] = sprintf('author-%s.twig', $params['id']);
                }

                $hierarchy[] = 'author.twig';
                break;
            case 'page':
                if (isset($params['slug'])) {
                    $hierarchy[] = sprintf('page-%s.twig', $params['slug']);
                }

                if (isset($params['id'])) {
                    $hierarchy[] = sprintf('page-%s.twig', $params['id']);
                }

                $hierarchy[] = 'page.twig';
                break;

            case 'category':
                // Category uses archive hierarchy but can have specific templates
                if (isset($params['slug'])) {
                    $hierarchy[] = sprintf('category-%s.twig', $params['slug']);
                }

                if (isset($params['id'])) {
                    $hierarchy[] = sprintf('category-%s.twig', $params['id']);
                }

                $hierarchy[] = 'category.twig';
                $hierarchy[] = 'archive.twig';
                break;

            case 'tag':
                if (isset($params['slug'])) {
                    $hierarchy[] = sprintf('tag-%s.twig', $params['slug']);
                }

                if (isset($params['id'])) {
                    $hierarchy[] = sprintf('tag-%s.twig', $params['id']);
                }

                $hierarchy[] = 'tag.twig';
                $hierarchy[] = 'archive.twig';
                break;

            case 'archive':
                if (isset($params['category'])) {
                    $hierarchy[] = sprintf('category-%s.twig', $params['category']);
                }

                $hierarchy[] = 'category.twig';
                $hierarchy[] = 'archive.twig';
                break;

            case 'search':
                $hierarchy[] = 'search.twig';
                break;

            case '404':
                $hierarchy[] = '404.twig';
                break;

            case 'home':
            case 'front-page':
                $hierarchy[] = 'home.twig';
                break;

            default:
                // No fallback - each content type must have its own template
                break;
        }

        return $hierarchy;
    }

    /**
     * Find the best template for a given context
     * Supports child themes by checking template hierarchy across theme chain
     */
    public static function getTemplate(string $context, array $params = []): ?string
    {
        $activeTheme = self::getActiveThemeLoaded();

        if (! $activeTheme) {
            return null;
        }

        $templateHierarchy = self::getTemplateHierarchy($context, $params);
        $themeHierarchy = self::getThemeHierarchy($activeTheme['directory']);
        $themesPath = self::getThemesPath();

        // Check each template variation across the theme chain (child -> parent -> grandparent)
        foreach ($templateHierarchy as $template) {
            foreach ($themeHierarchy as $themeDir) {
                $templatePath = $themesPath.'/'.$themeDir.'/templates/'.$template;
                if (File::exists($templatePath)) {
                    // Return template path relative to theme directory for Twig
                    // Twig will find it via its template directory chain
                    return 'templates/'.$template;
                }
            }
        }

        // No template found in hierarchy
        return null;
    }

    /**
     * Get available custom templates for a content type
     * Scans templates directory for files matching {type}-{name}.twig pattern
     *
     * @param  string  $type  Content type: 'post', 'page', 'category', 'tag'
     * @return array Options in format [['value' => 'landing', 'label' => 'Landing'], ...]
     */
    public static function getAvailableTemplates(string $type): array
    {
        $templates = [
            ['value' => '', 'label' => 'Default '.ucfirst($type)],
        ];

        $activeTheme = self::getActiveThemeLoaded();
        if (! $activeTheme) {
            return $templates;
        }

        $themeHierarchy = self::getThemeHierarchy($activeTheme['directory']);
        $themesPath = self::getThemesPath();
        $foundTemplates = [];

        // Scan all themes in hierarchy (child themes first)
        foreach ($themeHierarchy as $themeDir) {
            $templatesDir = $themesPath.'/'.$themeDir.'/templates';
            if (! File::isDirectory($templatesDir)) {
                continue;
            }

            // Look for files matching pattern: {type}-{name}.twig
            // Exclude default templates like page.twig, post.twig (no suffix)
            $files = File::glob($templatesDir.'/'.$type.'-*.twig');
            foreach ($files as $file) {
                $filename = pathinfo((string) $file, PATHINFO_FILENAME);

                // Extract template name: page-landing-design-one.twig → landing-design-one
                $templateName = Str::after($filename, $type.'-');
                // Skip if empty or already found (child theme takes precedence)
                if (empty($templateName)) {
                    continue;
                }

                if (isset($foundTemplates[$templateName])) {
                    continue;
                }

                // Skip slug-based or id-based templates (e.g., page-about.twig for a page with slug "about")
                // These are for specific content items, not custom templates
                // We allow templates with dashes in the name like: page-full-width.twig

                // Generate human-readable label: landing-design-one → "Landing Design One"
                $label = Str::title(str_replace('-', ' ', $templateName));
                $foundTemplates[$templateName] = true;
                $templates[] = ['value' => $templateName, 'label' => $label];
            }
        }

        return $templates;
    }

    /**
     * Check if a theme is protected from deletion
     */
    public static function isProtectedTheme(string $themeDirectory): bool
    {
        // Protect the default theme from deletion
        $protectedThemes = ['default'];

        return in_array($themeDirectory, $protectedThemes);
    }

    // =====================================================
    // CHILD THEME HELPER METHODS
    // =====================================================

    /**
     * Check if a theme is a child theme (has a parent)
     */
    public static function isChildTheme(string $themeDirectory): bool
    {
        $themeInfo = self::getThemeInfo($themeDirectory);

        return ! empty($themeInfo['parent']);
    }

    /**
     * Get the parent theme directory for a child theme
     */
    public static function getParentTheme(string $themeDirectory): ?string
    {
        $themeInfo = self::getThemeInfo($themeDirectory);

        return $themeInfo['parent'] ?? null;
    }

    /**
     * Get all child themes of a parent theme
     */
    public static function getChildThemes(string $parentDirectory): array
    {
        $allThemes = self::getAllThemes();
        $children = [];

        foreach ($allThemes as $theme) {
            $parentTheme = $theme['parent'] ?? null;
            if ($parentTheme === $parentDirectory) {
                $children[] = $theme;
            }
        }

        return $children;
    }

    /**
     * Check if a theme has child themes
     */
    public static function hasChildThemes(string $themeDirectory): bool
    {
        return self::getChildThemes($themeDirectory) !== [];
    }

    /**
     * Validate theme hierarchy to prevent circular dependencies
     *
     * @param  string  $childDirectory  The proposed child theme
     * @param  string  $parentDirectory  The proposed parent theme
     * @return bool True if hierarchy is valid, false if circular dependency detected
     */
    public static function validateThemeHierarchy(string $childDirectory, string $parentDirectory): bool
    {
        // Prevent self-parenting
        if ($childDirectory === $parentDirectory) {
            return false;
        }

        // Check if parent theme exists
        $parentInfo = self::getThemeInfo($parentDirectory);
        if (! $parentInfo) {
            return false;
        }

        // Prevent circular dependencies by traversing up the chain
        $visited = [$childDirectory];
        $currentParent = $parentDirectory;

        while ($currentParent) {
            // If we've seen this theme before, we have a circular dependency
            if (in_array($currentParent, $visited)) {
                return false;
            }

            $visited[] = $currentParent;
            $currentParent = self::getParentTheme($currentParent);
        }

        return true;
    }

    /**
     * Get the full theme hierarchy chain (child -> parent -> grandparent -> ...)
     */
    public static function getThemeHierarchy(string $themeDirectory): array
    {
        $hierarchy = [$themeDirectory];
        $currentParent = self::getParentTheme($themeDirectory);

        // Safety limit to prevent infinite loops in case of data corruption
        $maxDepth = 10;
        $depth = 0;

        while ($currentParent && $depth < $maxDepth) {
            $hierarchy[] = $currentParent;
            $currentParent = self::getParentTheme($currentParent);
            $depth++;
        }

        return $hierarchy;
    }

    /**
     * Flatten manifest data for view compatibility
     */
    private static function flattenManifestData(array $manifest): array
    {
        $flattened = $manifest;

        // Flatten author structure
        if (isset($manifest['author']) && is_array($manifest['author'])) {
            $flattened['author'] = $manifest['author']['name'] ?? '';
            $flattened['author_uri'] = $manifest['author']['uri'] ?? '';
            // Remove the nested author array
            unset($flattened['author']);
            $flattened['author'] = $manifest['author']['name'] ?? '';
        }

        // Flatten license structure
        if (isset($manifest['license']) && is_array($manifest['license'])) {
            $flattened['license'] = $manifest['license']['name'] ?? '';
            $flattened['license_uri'] = $manifest['license']['uri'] ?? '';
        }

        // Flatten requirements structure
        if (isset($manifest['requirements']) && is_array($manifest['requirements'])) {
            $flattened['requires_php'] = $manifest['requirements']['php'] ?? '8.0';
        }

        // Set defaults
        return array_merge([
            'name' => 'Unnamed Theme',
            'description' => '',
            'author' => '',
            'author_uri' => '',
            'theme_uri' => '',
            'version' => '1.0.0',
            'license' => '',
            'license_uri' => '',
            'tags' => [],
            'text_domain' => '',
            'requires_php' => '8.0',
            'supports' => [],
        ], $flattened);
    }

    /**
     * Get theme screenshot from manifest.json
     */
    private static function getThemeScreenshotFromManifest(array $themeInfo, string $themeDirectory): ?string
    {
        // Check if screenshot is specified in manifest
        if (isset($themeInfo['assets']['screenshot']) && $themeInfo['assets']['screenshot']) {
            $screenshotFile = $themeInfo['assets']['screenshot'];

            // SECURITY: Only allow image file extensions for screenshots
            $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
            $fileExtension = strtolower(pathinfo((string) $screenshotFile, PATHINFO_EXTENSION));

            if (! in_array($fileExtension, $allowedExtensions)) {
                Log::warning('SECURITY: Blocked non-image file as theme screenshot', [
                    'theme' => $themeDirectory,
                    'file' => $screenshotFile,
                    'extension' => $fileExtension,
                ]);

                return null;
            }

            // SECURITY: Block directory traversal attempts
            if (str_contains((string) $screenshotFile, '..') || str_contains((string) $screenshotFile, '\\')) {
                Log::warning('SECURITY: Blocked directory traversal in theme screenshot', [
                    'theme' => $themeDirectory,
                    'file' => $screenshotFile,
                ]);

                return null;
            }

            $screenshotPath = self::getThemesPath().'/'.$themeDirectory.'/'.$screenshotFile;

            // Verify the file actually exists
            if (File::exists($screenshotPath)) {
                return sprintf('/themes/%s/%s', $themeDirectory, $screenshotFile);
            }
        }

        return null;
    }

    /**
     * Get all template files for a theme
     */
    private static function getThemeTemplates(string $themeDirectory): array
    {
        $themePath = self::getThemesPath().'/'.$themeDirectory;
        $templates = [];
        $templatesPath = $themePath.'/templates';

        if (! File::isDirectory($templatesPath)) {
            return [];
        }

        $files = File::allFiles($templatesPath);

        foreach ($files as $file) {
            $filename = str_replace($templatesPath.'/', '', $file->getPathname());
            $extension = $file->getExtension();

            // Include Twig template files
            if ($extension === 'twig') {
                $templates[] = [
                    'filename' => $filename,
                    'name' => pathinfo($filename, PATHINFO_FILENAME),
                    'type' => self::getTemplateType($filename),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        }

        return $templates;
    }

    /**
     * Get all files for a theme (recursive file list)
     */
    private static function getThemeFiles(string $themeDirectory): array
    {
        $themePath = self::getThemesPath().'/'.$themeDirectory;
        $files = [];

        if (! File::exists($themePath)) {
            return [];
        }

        // Get all files recursively including subdirectories
        $allFiles = File::allFiles($themePath);

        foreach ($allFiles as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * Determine template type from filename (WordPress template hierarchy)
     */
    private static function getTemplateType(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Template hierarchy
        $templateTypes = [
            'index' => 'index',
            'home' => 'home',
            'front-page' => 'front-page',
            'post' => 'post',
            'single' => 'post', // Backward compatibility
            'page' => 'page',
            'archive' => 'archive',
            'category' => 'category',
            'tag' => 'tag',
            'author' => 'author',
            'date' => 'date',
            'search' => 'search',
            '404' => '404',
            'attachment' => 'attachment',
            'comments' => 'comments',
        ];

        foreach ($templateTypes as $type => $pattern) {
            if (Str::startsWith($name, $pattern)) {
                return $type;
            }
        }

        return 'custom';
    }

    /**
     * Check if a theme is currently active
     */
    private static function isActiveTheme(string $themeDirectory): bool
    {
        $activeThemeFile = self::getThemesPath().'/.active_theme';

        if (! File::exists($activeThemeFile)) {
            return false;
        }

        $activeTheme = trim(File::get($activeThemeFile));

        return $activeTheme === $themeDirectory;
    }
}
