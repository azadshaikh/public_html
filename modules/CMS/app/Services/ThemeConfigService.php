<?php

namespace Modules\CMS\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\CMS\Models\Theme;

class ThemeConfigService
{
    /**
     * Load theme configuration from JSON files instead of functions.php
     */
    public function loadThemeConfig(string $themeDirectory): array
    {
        $config = $this->getDefaultConfig();
        $themeHierarchy = Theme::getThemeHierarchy($themeDirectory);
        $themeHierarchy = array_reverse($themeHierarchy);

        foreach ($themeHierarchy as $directory) {
            $configPath = Theme::getThemesPath().'/'.$directory.'/config/config.json';

            if (! File::exists($configPath)) {
                continue;
            }

            $configContent = File::get($configPath);
            $themeConfig = json_decode($configContent, true);

            throw_if(json_last_error() !== JSON_ERROR_NONE, InvalidArgumentException::class, 'Invalid JSON in config.json for theme: '.$directory);

            $config = $this->mergeThemeConfig($config, $themeConfig);
        }

        return $config;
    }

    /**
     * Setup theme using secure configuration
     */
    public function setupTheme(string $themeDirectory): void
    {
        $config = $this->loadThemeConfig($themeDirectory);

        // Only set default options if they are missing
        if (isset($config['setup']['default_options'])) {
            $optionsFile = Theme::getThemesPath().'/'.$themeDirectory.'/config/options.json';
            $options = [];
            if (file_exists($optionsFile)) {
                $content = file_get_contents($optionsFile);
                $options = json_decode($content, true) ?: [];
            }

            $missing = false;
            foreach (array_keys($config['setup']['default_options']) as $key) {
                if (! array_key_exists($key, $options)) {
                    $missing = true;
                    break;
                }
            }

            if ($missing) {
                $this->setDefaultOptions($config['setup']['default_options']);
            }
        }

        // Only register sidebars if they are missing
        if (isset($config['widgets']['sidebars'])) {
            $sidebars = $config['widgets']['sidebars'];
            $optionsFile = Theme::getThemesPath().'/'.$themeDirectory.'/config/options.json';
            $options = [];
            if (file_exists($optionsFile)) {
                $content = file_get_contents($optionsFile);
                $options = json_decode($content, true) ?: [];
            }

            $missingSidebar = false;
            foreach ($sidebars as $sidebar) {
                $sidebarKey = 'sidebar_'.($sidebar['id'] ?? '');
                if (! array_key_exists($sidebarKey, $options)) {
                    $missingSidebar = true;
                    break;
                }
            }

            if ($missingSidebar) {
                $this->registerSidebars($sidebars);
            }
        }
    }

    /**
     * Get theme customizer settings from JSON config
     */
    public function getCustomizerSettings(string $themeDirectory): array
    {
        $cacheKey = 'theme_customizer_settings_'.$themeDirectory;
        $signature = $this->getConfigSignature($themeDirectory);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['signature'] ?? null) === $signature) {
            return $cached['data'] ?? [];
        }

        $config = $this->loadThemeConfig($themeDirectory);
        $customizer = $config['customizer'] ?? [];

        Cache::forever($cacheKey, [
            'signature' => $signature,
            'data' => $customizer,
        ]);

        return $customizer;
    }

    /**
     * Generate custom CSS from theme configuration
     */
    public function generateCustomCSS(string $themeDirectory): string
    {
        $config = $this->loadThemeConfig($themeDirectory);
        $cssConfig = $config['css_generation'] ?? [];

        $css = '';

        // Generate CSS variables
        if (! empty($cssConfig['variables'])) {
            $selectors = $cssConfig['variable_selectors'] ?? [':root', "[data-bs-theme='light']"];

            if (! empty($selectors)) {
                $css .= implode(', ', $selectors)." {\n";
                foreach ($cssConfig['variables'] as $property => $value) {
                    // Safely process template variables
                    $processedValue = $this->processTemplateVariable($value);
                    $css .= "    {$property}: {$processedValue};\n";
                }

                $css .= "}\n\n";
            }
        }

        // Add custom CSS rules
        if (isset($cssConfig['custom_rules'])) {
            foreach ($cssConfig['custom_rules'] as $rule) {
                $css .= $rule."\n";
            }
        }

        // Generate conditional CSS
        if (isset($cssConfig['conditional'])) {
            foreach ($cssConfig['conditional'] as $condition => $rules) {
                if ($this->evaluateCondition($condition)) {
                    foreach ($rules as $rule) {
                        $css .= $rule."\n";
                    }
                }
            }
        }

        // Append user custom CSS from theme options (after generated CSS so it can override)
        $userCustomCss = $this->getUserCustomCss();
        if ($userCustomCss !== '') {
            $css .= "\n/* User Custom CSS */\n";
            $css .= $userCustomCss;
        }

        return $css;
    }

    /**
     * Validate theme configuration
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        // Validate structure
        $allowedSections = ['setup', 'customizer', 'css_generation', 'widgets'];
        foreach (array_keys($config) as $section) {
            if (! in_array($section, $allowedSections)) {
                $errors[] = 'Invalid configuration section: '.$section;
            }
        }

        // Validate customizer section
        if (isset($config['customizer']['sections'])) {
            foreach ($config['customizer']['sections'] as $sectionId => $section) {
                if (! isset($section['title']) || ! isset($section['settings'])) {
                    $errors[] = 'Invalid customizer section: '.$sectionId;
                }

                if (isset($section['settings'])) {
                    foreach ($section['settings'] as $settingId => $setting) {
                        if (! isset($setting['type']) || ! isset($setting['label'])) {
                            $errors[] = sprintf('Invalid setting: %s in section %s', $settingId, $sectionId);
                        }

                        $allowedTypes = ['text', 'textarea', 'color', 'select', 'checkbox', 'image', 'url', 'email', 'code_editor'];
                        if (! in_array($setting['type'], $allowedTypes)) {
                            $errors[] = sprintf('Invalid setting type: %s for %s', $setting['type'], $settingId);
                        }
                    }
                }
            }
        }

        // Validate CSS generation
        if (isset($config['css_generation']['variables'])) {
            foreach ($config['css_generation']['variables'] as $property => $value) {
                if (! is_string($property) || ! is_string($value)) {
                    $errors[] = 'Invalid CSS variable definition';
                }
            }
        }

        return $errors;
    }

    /**
     * Set default theme options safely
     */
    public function setDefaultOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            // Sanitize option key and value
            $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $key);

            if (strlen((string) $key) > 50) {
                continue; // Skip overly long keys
            }

            if (theme_get_option($key) === null) {
                theme_set_option($key, $value);
            }
        }
    }

    /**
     * Register theme sidebars safely
     */
    public function registerSidebars(array $sidebars): void
    {
        foreach ($sidebars as $sidebar) {
            if (! isset($sidebar['id'])) {
                continue;
            }

            if (! isset($sidebar['name'])) {
                continue;
            }

            // Sanitize sidebar data
            $sidebar['id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $sidebar['id']);
            $sidebar['name'] = strip_tags((string) $sidebar['name']);

            // Store sidebar configuration (you can implement registration logic here)
            theme_set_option('sidebar_'.$sidebar['id'], $sidebar);
        }
    }

    /**
     * Auto-discover theme widgets (folder-based only)
     *
     * @return array<array{name: mixed, description: mixed, category: mixed, settings_schema?: mixed}>
     */
    public function getThemeWidgets($themeDirectory = null): array
    {
        if (! $themeDirectory) {
            $themeDirectory = theme_get_option('active_theme', 'default');
        }

        $widgetsPath = base_path(sprintf('themes/%s/widgets', $themeDirectory));
        $themeWidgets = [];

        if (! is_dir($widgetsPath)) {
            return $themeWidgets;
        }

        try {
            // Scan for folder-based widgets only
            $folders = glob($widgetsPath.'/*', GLOB_ONLYDIR);

            foreach ($folders as $folder) {
                $widgetId = basename($folder);
                $manifestPath = $folder.'/widget.json';

                // Skip if widget name contains invalid characters
                if (! preg_match('/^[a-z0-9_-]+$/', $widgetId)) {
                    continue;
                }

                if (file_exists($manifestPath)) {
                    try {
                        $manifest = json_decode(file_get_contents($manifestPath), true);
                        if ($manifest) {
                            $themeWidgets[$widgetId] = [
                                'name' => $manifest['name'] ?? ucwords(str_replace(['-', '_'], ' ', $widgetId)),
                                'description' => $manifest['description'] ?? sprintf('Custom %s widget', $widgetId),
                                'category' => $manifest['category'] ?? 'Widgets',
                                'settings_schema' => $manifest['settings'] ?? [],
                            ];
                        }
                    } catch (Exception) {
                        // Fallback for invalid manifest
                        $themeWidgets[$widgetId] = [
                            'name' => ucwords(str_replace(['-', '_'], ' ', $widgetId)),
                            'description' => sprintf('Custom %s widget from %s theme', $widgetId, $themeDirectory),
                            'category' => 'Widgets',
                        ];
                    }
                }
            }
        } catch (Exception $exception) {
            Log::warning('Failed to discover theme widgets: '.$exception->getMessage());
        }

        return $themeWidgets;
    }

    /**
     * Get all available widgets (folder-based only)
     */
    public function getAllAvailableWidgets($themeDirectory = null): array
    {
        return $this->getThemeWidgets($themeDirectory);
    }

    /**
     * Set multiple theme options at once (avoids race conditions)
     */
    public function setThemeOptions(string $themeDirectory, array $settings): void
    {
        $optionsFile = Theme::getThemesPath().'/'.$themeDirectory.'/config/options.json';

        // Get current options
        $options = [];
        if (file_exists($optionsFile)) {
            $content = file_get_contents($optionsFile);
            $options = json_decode($content, true) ?: [];
        }

        // Set all the options at once
        foreach ($settings as $key => $value) {
            $options[$key] = $value;
        }

        // Save options
        $directory = dirname($optionsFile);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($optionsFile, json_encode($options, JSON_PRETTY_PRINT));

        // Clear cache - MUST use the same cache key format as theme_get_option()
        $activeTheme = Theme::getActiveTheme();
        if ($activeTheme && $activeTheme['directory'] === $themeDirectory) {
            $cacheKey = 'cms_theme_options_'.$activeTheme['directory'];
            Cache::forget($cacheKey);
        }
    }

    /**
     * Get user custom CSS from theme options (base64 decoded and sanitized)
     */
    protected function getUserCustomCss(): string
    {
        $customCss = theme_get_option('custom_css', '');

        if (empty($customCss)) {
            return '';
        }

        // Decode from base64
        $decoded = base64_decode($customCss, true);
        if ($decoded !== false) {
            $customCss = $decoded;
        }

        // Sanitize: strip script tags for security
        return preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $customCss);
    }

    /**
     * Get default configuration structure
     */
    private function getDefaultConfig(): array
    {
        return [
            'setup' => [
                'default_options' => [],
            ],
            'customizer' => [
                'sections' => [],
            ],
            'css_generation' => [
                'variables' => [],
                'custom_rules' => [],
            ],
            'widgets' => [
                'sidebars' => [],
            ],
        ];
    }

    /**
     * Merge theme configurations with inheritance support.
     * Child values override parent values while preserving missing keys.
     */
    private function mergeThemeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                if (array_is_list($value) || array_is_list($base[$key])) {
                    $base[$key] = $value === [] ? $base[$key] : $value;
                } else {
                    $base[$key] = $this->mergeThemeConfig($base[$key], $value);
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Build a signature for theme config files across the inheritance chain.
     */
    private function getConfigSignature(string $themeDirectory): string
    {
        $themeHierarchy = Theme::getThemeHierarchy($themeDirectory);
        $segments = [];

        foreach ($themeHierarchy as $directory) {
            $configPath = Theme::getThemesPath().'/'.$directory.'/config/config.json';
            if (File::exists($configPath)) {
                $segments[] = $directory.':'.File::lastModified($configPath);
            }
        }

        return sha1(implode('|', $segments));
    }

    /**
     * Convert hex color to rgb color string.
     */
    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } elseif (strlen($hex) === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            // Return a default or handle error for invalid hex
            return '0, 0, 0';
        }

        return sprintf('%s, %s, %s', $r, $g, $b);
    }

    /**
     * Process template variables safely
     */
    private function processTemplateVariable(string $value): string
    {
        // Only allow theme_get_option function calls, with an optional 'to_rgb' filter
        if (preg_match('/^{{\s*theme_get_option\s*\(\s*["\']([^"\']+)["\']\s*(?:,\s*["\']([^"\']*)["\'])?\s*\)\s*(?:\|\s*(to_rgb))?\s*}}$/', $value, $matches)) {
            $optionKey = $matches[1];
            $defaultValue = $matches[2] ?? '';
            $filter = $matches[3] ?? null;

            $optionValue = theme_get_option($optionKey, $defaultValue);

            if ($filter === 'to_rgb') {
                return $this->hexToRgb($optionValue);
            }

            return $optionValue;
        }

        return $value;
    }

    /**
     * Evaluate conditions safely
     */
    private function evaluateCondition(string $condition): bool
    {
        // Only allow specific safe conditions
        if (preg_match('/^theme_get_option\s*\(\s*["\']([^"\']+)["\']\s*\)\s*===?\s*true$/', $condition, $matches)) {
            return theme_get_option($matches[1]) === true;
        }

        if (preg_match('/^theme_get_option\s*\(\s*["\']([^"\']+)["\']\s*\)\s*===?\s*false$/', $condition, $matches)) {
            return theme_get_option($matches[1]) === false;
        }

        return false;
    }
}
