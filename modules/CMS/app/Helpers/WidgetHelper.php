<?php

use Modules\CMS\Models\Theme;
use Modules\CMS\Services\ThemeConfigService;
use Modules\CMS\Services\TwigService;

if (! function_exists('render_widget_area')) {
    /**
     * Render all widgets in a specific widget area
     *
     * @param  string  $areaId  The widget area ID (e.g., 'sidebar-main', 'footer-1')
     * @return string Rendered HTML for all widgets in the area
     */
    function render_widget_area(string $areaId): string
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return '';
        }

        $widgets = theme_get_option('widgets_'.$areaId, []);

        if (empty($widgets)) {
            return '';
        }

        // Sort widgets by position
        usort($widgets, fn (array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        $html = '';
        foreach ($widgets as $widget) {
            $html .= render_widget($widget);
        }

        return $html;
    }
}

if (! function_exists('render_widget')) {
    /**
     * Render a single widget (folder-based only)
     * Supports parent theme fallback for child themes
     *
     * @param  array  $widget  Widget configuration array
     * @return string Rendered widget HTML
     */
    function render_widget(array $widget): string
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return '';
        }

        if (empty($widget['type'])) {
            return '';
        }

        $widgetId = $widget['type'];
        $title = $widget['title'] ?? '';
        $settings = $widget['settings'] ?? [];

        // Render folder-based widget from current theme or parent themes
        $activeTheme = Theme::getActiveTheme();
        if ($activeTheme) {
            // Get theme hierarchy (child -> parent -> grandparent...)
            $themeHierarchy = Theme::getThemeHierarchy($activeTheme['directory']);

            // Search for widget in theme hierarchy
            foreach ($themeHierarchy as $themeDir) {
                $themeWidgetFolderPath = Theme::getThemesPath().'/'.$themeDir.'/widgets/'.$widgetId;
                $twigWidgetFilePath = $themeWidgetFolderPath.sprintf('/%s.twig', $widgetId);

                // Render Twig widget (.twig) if found in this theme
                if (is_dir($themeWidgetFolderPath) && file_exists($twigWidgetFilePath)) {
                    try {
                        $twigService = resolve(TwigService::class);
                        // Set active theme (TwigService already handles parent theme paths for inheritance)
                        $twigService->setTheme($activeTheme['directory']);

                        return $twigService->render('widgets/'.$widgetId.'/'.$widgetId.'.twig', [
                            'title' => $title,
                            'settings' => $settings,
                        ]);
                    } catch (Exception $e) {
                        if (config('app.debug')) {
                            // Show error inline in debug mode
                            return '<div style="background:#dc3545;color:white;padding:15px;margin:10px 0;border-radius:4px;">
                                <strong>Widget Error: '.$widgetId.'</strong><br>
                                <small>'.htmlspecialchars($e->getMessage()).'</small><br>
                                <small style="opacity:0.8;">File: '.basename($e->getFile()).' (Line: '.$e->getLine().')</small>
                            </div>';
                        }

                        return '';
                    }
                }
            }
        }

        // Widget not found in any theme in hierarchy
        if (config('app.debug')) {
            return sprintf("<!-- Widget '%s' not found in theme hierarchy -->", $widgetId);
        }

        return '';
    }
}

if (! function_exists('has_widgets')) {
    /**
     * Check if a widget area has any widgets
     *
     * @param  string  $areaId  The widget area ID
     * @return bool True if the area has widgets, false otherwise
     */
    function has_widgets(string $areaId): bool
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return false;
        }

        $widgets = theme_get_option('widgets_'.$areaId, []);

        return ! empty($widgets);
    }
}

if (! function_exists('get_widget_areas')) {
    /**
     * Get all widget areas defined in the current theme's config
     *
     * @return array Array of widget areas from the sidebars config
     */
    function get_widget_areas(): array
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return [];
        }

        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return [];
        }

        $configFile = Theme::getThemesPath().'/'.$activeTheme['directory'].'/config/config.json';

        if (! file_exists($configFile)) {
            return [];
        }

        $config = json_decode(file_get_contents($configFile), true) ?: [];

        return $config['widgets']['sidebars'] ?? [];
    }
}

if (! function_exists('get_available_widgets')) {
    /**
     * Get all available widgets (folder-based only)
     *
     * @return array Array of available widgets
     */
    function get_available_widgets(): array
    {
        // Check if CMS module is enabled
        if (! active_modules('cms')) {
            return [];
        }

        try {
            // Use ThemeConfigService for widget discovery
            $themeConfigService = resolve(ThemeConfigService::class);

            return $themeConfigService->getAllAvailableWidgets();
        } catch (Exception) {
            // Fallback to basic widget discovery if service fails
            $widgets = [];

            // Discover all folder-based widgets from current theme
            $activeTheme = Theme::getActiveTheme();
            if ($activeTheme) {
                $themeWidgetsPath = Theme::getThemesPath().'/'.$activeTheme['directory'].'/widgets';

                if (is_dir($themeWidgetsPath)) {
                    // Scan for folder-based widgets
                    $folders = glob($themeWidgetsPath.'/*', GLOB_ONLYDIR);
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
                                    $widgets[$widgetId] = [
                                        'name' => $manifest['name'] ?? ucwords(str_replace(['-', '_'], ' ', $widgetId)),
                                        'description' => $manifest['description'] ?? sprintf('Custom %s widget', $widgetId),
                                        'category' => $manifest['category'] ?? 'Widgets',
                                        'settings_schema' => $manifest['settings'] ?? [],
                                    ];
                                }
                            } catch (Exception) {
                                // Fallback for invalid manifest
                                $widgets[$widgetId] = [
                                    'name' => ucwords(str_replace(['-', '_'], ' ', $widgetId)),
                                    'description' => sprintf('Custom %s widget from %s theme', $widgetId, $activeTheme['directory']),
                                    'category' => 'Widgets',
                                ];
                            }
                        }
                    }
                }
            }

            return $widgets;
        }
    }
}
