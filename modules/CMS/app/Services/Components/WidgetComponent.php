<?php

namespace Modules\CMS\Services\Components;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\TwigService;

/**
 * Widget Component
 * Renders theme widgets with parent theme fallback support
 * Usage: {widget type='banner' settings=$widgetSettings}
 */
class WidgetComponent extends ThemeComponent
{
    public function render(array $params, $template = null): string
    {
        $type = $this->param($params, 'type');
        $settings = $this->param($params, 'settings', []);

        if (! $type) {
            return '';
        }

        // Get active theme
        $activeTheme = Theme::getActiveTheme();
        if (! $activeTheme) {
            return '';
        }

        // Get theme hierarchy for parent fallback
        $themeHierarchy = Theme::getThemeHierarchy($activeTheme['directory']);

        // Build widget template path and search in hierarchy
        $widgetTemplate = 'widgets/'.$type.'/'.$type.'.twig';
        $foundPath = null;

        foreach ($themeHierarchy as $themeDir) {
            $themePath = Theme::getThemesPath().'/'.$themeDir;
            $fullPath = $themePath.'/'.$widgetTemplate;

            if (file_exists($fullPath)) {
                $foundPath = $fullPath;
                break;
            }
        }

        // Check if widget template exists in any theme in hierarchy
        if (! $foundPath) {
            Log::warning('Widget template not found in theme hierarchy', [
                'type' => $type,
                'hierarchy' => $themeHierarchy,
            ]);

            return '';
        }

        // Render widget using Twig (TwigService handles path resolution)
        try {
            $twig = resolve(TwigService::class);
            $twig->setTheme($activeTheme['directory']);

            return $twig->render($widgetTemplate, ['settings' => $settings]);
        } catch (Exception $exception) {
            Log::error('Twig widget rendering failed', [
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }
}
