<?php

use App\Models\Settings;
use App\Services\SettingsService;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\ContentSanitizer;
use Modules\CMS\Services\ThemeAssetResolver;
use Modules\CMS\Services\ThemeConfigService;
use Modules\CMS\Services\ThemeOptionsCacheService;

if (! function_exists('theme_get_option')) {
    /**
     * Get a theme option value (sanitized for security)
     *
     * Branding fields (site_title, logo, favicon, primary_color, secondary_color) are redirected to
     * App Settings for a single source of truth across all themes.
     *
     * Uses ThemeOptionsCacheService for two-tier caching (memory + persistent).
     */
    function theme_get_option(string $key, $default = null): mixed
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return $default;
        }

        // Branding keys are redirected to App Settings (single source of truth)
        $brandingRedirects = [
            'site_title' => 'site_title',
            'logo' => 'site_logo',
            'favicon' => 'favicon',
            'primary_color' => 'branding_primary_color',
            'secondary_color' => 'branding_secondary_color',
        ];

        if (isset($brandingRedirects[$key])) {
            return setting($brandingRedirects[$key], $default);
        }

        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return $default;
        }

        $value = resolve(ThemeOptionsCacheService::class)->getOption($key, $default);

        // Decode HTML entities for string values to prevent double-encoding in forms
        if (is_string($value)) {
            return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }

        return $value;
    }
}

if (! function_exists('theme_set_option')) {
    /**
     * Set a theme option value
     *
     * Uses ThemeOptionsCacheService for cache invalidation.
     * Accepts strings, arrays, or null. Arrays are stored natively in JSON.
     */
    function theme_set_option(string $key, mixed $value): bool
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return false;
        }

        // Branding keys are redirected to App Settings (single source of truth)
        $brandingRedirects = [
            'site_title' => 'site_title',
            'logo' => 'site_logo',
            'favicon' => 'favicon',
            'primary_color' => 'branding_primary_color',
            'secondary_color' => 'branding_secondary_color',
        ];

        if (isset($brandingRedirects[$key])) {
            try {
                $appSettingKey = $brandingRedirects[$key];
                $group = $appSettingKey === 'site_title' ? 'seo' : 'general';

                $setting = Settings::query()->firstOrNew([
                    'group' => $group,
                    'key' => $appSettingKey,
                ]);

                $setting->value = $value ?? '';

                if (empty($setting->type)) {
                    $setting->type = 'string';
                }

                $userId = auth()->id();
                if (! $setting->exists && $userId) {
                    $setting->created_by = $userId;
                }

                if ($userId) {
                    $setting->updated_by = $userId;
                }

                $setting->save();

                // Keep env-derived app.name/mail name in sync when possible
                if ($key === 'site_title' && ! in_array($value, [null, '', '0'], true)) {
                    $settingsService = new SettingsService;
                    $settingsService->updateEnvironmentVariable('APP_NAME', '"'.$value.'"');
                    $settingsService->updateEnvironmentVariable('MAIL_FROM_NAME', '"'.$value.'"');
                }

                // Note: Settings cache is automatically invalidated by SettingsObserver

                return true;
            } catch (Exception) {
                return false;
            }
        }

        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return false;
        }

        $optionsFile = Theme::getThemesPath().'/'.$activeTheme['directory'].'/config/options.json';

        // Get current options
        $options = [];
        if (file_exists($optionsFile)) {
            $content = file_get_contents($optionsFile);
            $options = json_decode($content, true) ?: [];
        }

        // Set the option
        $options[$key] = $value;

        // Save options
        $directory = dirname($optionsFile);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $success = file_put_contents($optionsFile, json_encode($options, JSON_PRETTY_PRINT)) !== false;

        if ($success) {
            // Invalidate cache using ThemeOptionsCacheService (clears both memory and persistent)
            resolve(ThemeOptionsCacheService::class)->invalidateTheme($activeTheme['directory'], 'theme_set_option');
        }

        return $success;
    }
}

if (! function_exists('theme_get_template_directory_uri')) {
    /**
     * Get the active theme's template directory URI
     */
    function theme_get_template_directory_uri(): string
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return '';
        }

        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return '';
        }

        return url('themes/'.$activeTheme['directory']);
    }
}

if (! function_exists('theme_asset')) {
    /**
     * Get a theme asset URL from the assets folder with automatic versioning
     * Supports child themes - checks child theme first, then falls back to parent theme(s)
     * Uses request-level caching to minimize file system checks
     * SECURITY: Only allows static asset files, blocks PHP and other executable files
     *
     * @param  string  $path  Path to asset relative to theme's assets folder (e.g., 'css/style.css', 'js/theme.js', 'img/logo.png')
     * @param  bool  $version  Whether to add version hash for cache busting (default: true)
     * @return string Full URL to the asset, or empty string if file type is not allowed
     */
    function theme_asset(string $path, bool $version = true): string
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return asset($path); // Fallback to regular asset helper
        }

        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return '';
        }

        // Remove leading slash if present
        $path = ltrim($path, '/');

        // Security: Block directory traversal attempts
        if (str_contains($path, '..') || str_contains($path, '\\')) {
            return '';
        }

        // Security: Only allow specific static asset file extensions
        $allowedExtensions = [
            // Stylesheets
            'css', 'scss', 'sass', 'less',
            // JavaScript
            'js', 'mjs', 'ts',
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp',
            // Fonts
            'woff', 'woff2', 'ttf', 'otf', 'eot',
            // Documents
            'pdf', 'txt', 'md',
            // Media
            'mp4', 'webm', 'ogg', 'mp3', 'wav',
            // Data
            'json', 'xml', 'csv',
            // Archives (for downloadable assets)
            'zip', 'tar', 'gz',
        ];

        $fileExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Security: Block if extension is not in allowed list
        if (! in_array($fileExtension, $allowedExtensions)) {
            // Log security attempt for monitoring
            if (config('app.debug')) {
                error_log(sprintf('SECURITY: Blocked theme_asset() access to file with extension: %s (path: %s)', $fileExtension, $path));
            }

            return '';
        }

        // Security: Explicitly block dangerous file patterns
        $dangerousPatterns = [
            '/\.php/i',
            '/\.phtml/i',
            '/\.php\d/i',
            '/\.phar/i',
            '/\.htaccess/i',
            '/\.env/i',
            '/\.ini/i',
            '/\.conf/i',
            '/\.sh/i',
            '/\.bat/i',
            '/\.exe/i',
            '/\.dll/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                if (config('app.debug')) {
                    error_log('SECURITY: Blocked theme_asset() access to dangerous file pattern: '.$path);
                }

                return '';
            }
        }

        // Resolve asset across theme hierarchy (child -> parent)
        // Uses request-level caching to avoid repeated file_exists calls
        $resolvedThemeDir = ThemeAssetResolver::resolve($path);

        // If asset not found in any theme, fall back to active theme URL (may 404)
        $themeDir = $resolvedThemeDir ?? $activeTheme['directory'];

        // Base URL construction
        $baseUrl = url('themes/'.$themeDir.'/assets/'.$path);

        // Add version hash for cache busting if requested
        if ($version) {
            $assetPath = ThemeAssetResolver::getFullPath($path);
            if ($assetPath && file_exists($assetPath)) {
                $hash = substr(md5_file($assetPath), 0, 8);
                $baseUrl .= '?v='.$hash;
            } else {
                // Fallback to theme version if file doesn't exist
                $baseUrl .= '?v='.($activeTheme['version'] ?? '1.0.0');
            }
        }

        return $baseUrl;
    }
}

if (! function_exists('theme_setup')) {
    /**
     * Setup the active theme (call theme setup functions) - singleton pattern
     */
    function theme_setup(): void
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return;
        }

        // Check if theme is already loaded to prevent redundant loading
        if (Theme::isThemeLoaded()) {
            return;
        }

        $activeTheme = Theme::getActiveTheme();
        if (! $activeTheme) {
            return;
        }

        // Use Theme::loadTheme which implements singleton pattern
        Theme::loadTheme($activeTheme['directory']);
    }
}

if (! function_exists('theme_get_customizer_settings')) {
    /**
     * Get theme customizer settings (secure)
     */
    function theme_get_customizer_settings(?string $themeDirectory = null): array
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return [];
        }

        if (! $themeDirectory) {
            $activeTheme = Theme::getActiveTheme();
            if (! $activeTheme) {
                return [];
            }

            $themeDirectory = $activeTheme['directory'];
        }

        // Use secure configuration service
        $configService = resolve(ThemeConfigService::class);

        return $configService->getCustomizerSettings($themeDirectory);
    }
}

if (! function_exists('theme_generate_custom_css')) {
    /**
     * Generate custom CSS from theme configuration (secure)
     */
    function theme_generate_custom_css(?string $themeDirectory = null): string
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return '';
        }

        if (! $themeDirectory) {
            $activeTheme = Theme::getActiveTheme();
            if (! $activeTheme) {
                return '';
            }

            $themeDirectory = $activeTheme['directory'];
        }

        // Use secure configuration service
        $configService = resolve(ThemeConfigService::class);

        return $configService->generateCustomCSS($themeDirectory);
    }
}

if (! function_exists('safe_content')) {
    /**
     * Sanitize HTML content for safe output
     *
     * @param  string|null  $content  Content to sanitize
     * @param  array|null  $allowedTags  Optional array of allowed HTML tags
     * @return string Sanitized content
     */
    function safe_content(?string $content, ?array $allowedTags = null): string
    {
        if (in_array($content, [null, '', '0'], true)) {
            return '';
        }

        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return strip_tags($content); // Basic fallback
        }

        return ContentSanitizer::sanitizeHTML($content, $allowedTags);
    }
}
