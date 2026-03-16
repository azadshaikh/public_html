<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\RecacheApplication;
use App\Traits\ActivityTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\CMS\Models\Theme;

class WidgetController extends Controller implements HasMiddleware
{
    use ActivityTrait;

    public const MODULE_PATH = 'cms::widgets';

    protected string $activityLogModule = 'CMS Widgets';

    protected string $activityEntityAttribute = 'name';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_widgets', only: ['index', 'edit']),
            new Middleware('permission:edit_widgets', only: ['saveAllWidgets']),
        ];
    }

    /**
     * Display the widget management interface
     */
    public function index(): View
    {
        $widgetAreas = get_widget_areas();
        $currentWidgets = [];
        foreach ($widgetAreas as $area) {
            $currentWidgets[$area['id']] = theme_get_option('widgets_'.$area['id'], []);
        }

        $availableWidgets = get_available_widgets();

        /** @var view-string $view */
        $view = self::MODULE_PATH.'.index';

        return view($view, ['widgetAreas' => $widgetAreas, 'currentWidgets' => $currentWidgets, 'availableWidgets' => $availableWidgets]);
    }

    /**
     * Display the widget management interface for a specific area.
     */
    public function edit(string $areaId): View
    {
        $allWidgetAreas = get_widget_areas();

        // Find the specific area we are editing to validate it and get its details
        $targetArea = collect($allWidgetAreas)->firstWhere('id', $areaId);

        abort_unless($targetArea, 404, 'Widget area not found.');

        // We pass only the target area to the view, but as an array to match the editor's expectation
        $widgetAreas = [$targetArea];
        $availableWidgets = get_available_widgets();

        // Get current widgets for the target area only
        $currentWidgets = [];
        $currentWidgets[$areaId] = theme_get_option('widgets_'.$areaId, []);

        /** @var view-string $view */
        $view = self::MODULE_PATH.'.edit';

        return view($view, ['widgetAreas' => $widgetAreas, 'availableWidgets' => $availableWidgets, 'currentWidgets' => $currentWidgets]);
    }

    /**
     * Save all widgets for all areas (AJAX) - Folder-based widgets only
     */
    public function saveAllWidgets(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'widgets' => ['required', 'array'],
            'widgets.*' => ['array'], // each area is an array of widgets
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data structure.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $allWidgetsData = $request->input('widgets', []);
        $availableWidgets = get_available_widgets();
        $widgetAreas = get_widget_areas();
        $areaIds = array_map(fn (array $area) => $area['id'], $widgetAreas);
        $updatedAreas = [];
        $totalWidgets = 0;

        foreach ($allWidgetsData as $areaId => $widgets) {
            // Validate area exists
            if (! in_array($areaId, $areaIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid widget area: '.$areaId,
                ], 400);
            }

            // Sanitize and validate widget data for the area
            $sanitizedWidgets = [];
            foreach ($widgets as $index => $widget) {
                if (empty($widget['type']) || ! isset($availableWidgets[$widget['type']])) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf("Widget type '%s' is not available", $widget['type']),
                    ], 400);
                }

                $sanitizedWidgets[] = [
                    'id' => $widget['id'] ?? 'widget-'.time().'-'.Str::random(6),
                    'type' => $widget['type'],
                    'title' => $widget['title'] ?? '',
                    'settings' => $widget['settings'] ?? [],
                    'position' => $index,
                ];
            }

            // Save to theme options (store as native array for usort/render compatibility)
            theme_set_option('widgets_'.$areaId, $sanitizedWidgets);
            $this->clearWidgetCaches();
            $updatedAreas[] = $areaId;
            $totalWidgets += count($sanitizedWidgets);
        }

        // Dispatch job to rebuild all caches asynchronously (non-blocking)
        dispatch(new RecacheApplication('Widgets update: '.implode(', ', $updatedAreas)));

        $activeTheme = Theme::getActiveTheme();
        if ($activeTheme) {
            $themeModel = $this->resolveThemeModelForLogging($activeTheme);

            $this->activity($themeModel)
                ->extra([
                    'module' => 'Widgets',
                    'theme_directory' => $activeTheme['directory'] ?? null,
                    'areas_updated' => $updatedAreas,
                    'total_widgets' => $totalWidgets,
                ])
                ->updated('Theme widgets updated successfully.');
        }

        return response()->json([
            'success' => true,
            'message' => 'All widgets saved successfully!',
        ]);
    }

    /**
     * Clear widget-related caches for a specific area.
     */
    private function clearWidgetCaches(): void
    {
        $activeTheme = Theme::getActiveTheme();
        if ($activeTheme) {
            $cacheKey = 'cms_theme_options_'.$activeTheme['directory'];
            Cache::forget($cacheKey);
        }
    }

    private function resolveThemeModelForLogging(?array $theme): Theme
    {
        $directory = $theme['directory'] ?? 'unknown-theme';
        $themeModel = new Theme;
        $themeModel->setAttribute('id', 0);
        $themeModel->setAttribute('directory', $directory);
        $themeModel->setAttribute('name', $theme['name'] ?? Str::headline($directory));
        $themeModel->setAttribute('is_active', $theme['is_active'] ?? false);

        return $themeModel;
    }
}
