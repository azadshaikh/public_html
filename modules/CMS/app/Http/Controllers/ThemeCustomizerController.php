<?php

namespace Modules\CMS\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\RecacheApplication;
use App\Models\Settings;
use App\Services\SettingsService;
use App\Traits\HasMediaPicker;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\FrontendFaviconService;
use Modules\CMS\Services\ThemeConfigService;
use Modules\CMS\Services\ThemeOptionsCacheService;

class ThemeCustomizerController extends Controller implements HasMiddleware
{
    use ActivityTrait;
    use HasMediaPicker;

    protected string $activityLogModule = 'Theme Customizer';

    protected string $activityEntityAttribute = 'name';

    public function __construct(
        private readonly ThemeOptionsCacheService $themeOptionsCacheService,
        private readonly FrontendFaviconService $frontendFaviconService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_themes', only: [
                'index',
                'export',
            ]),
            new Middleware('permission:edit_themes', only: [
                'update',
                'previewCSS',
                'reset',
                'import',
            ]),
        ];
    }

    /**
     * Show the theme customizer interface
     */
    public function index(Request $request): Response|RedirectResponse
    {
        try {
            $activeTheme = Theme::getActiveTheme();

            if (! $activeTheme) {
                return to_route('cms.appearance.themes.index')
                    ->with('error', 'No active theme found. Please activate a theme first.');
            }

            $previewUrl = $request->query('preview_url', url('/'));

            return Inertia::render('cms/themes/customizer/index', [
                'activeTheme' => $this->mapThemeSummary($activeTheme),
                'sections' => $this->buildCustomizerSections($activeTheme['directory']),
                'initialValues' => $this->decodeCodeEditorValues(
                    $activeTheme['directory'],
                    $this->getCurrentValues($activeTheme['directory'])
                ),
                'previewUrl' => $previewUrl,
                ...$this->getMediaPickerProps(),
            ]);
        } catch (Exception $exception) {
            Log::error('Theme customizer index error: '.$exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return to_route('cms.appearance.themes.index')
                ->with('error', 'Failed to load theme customizer: '.$exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    private function mapThemeSummary(array $theme): array
    {
        return [
            'name' => $theme['name'] ?? Str::headline((string) ($theme['directory'] ?? 'Theme')),
            'directory' => $theme['directory'] ?? null,
            'description' => $theme['description'] ?? null,
            'screenshot' => $theme['screenshot'] ?? null,
            'author' => $theme['author'] ?? null,
            'version' => $theme['version'] ?? null,
            'is_child' => $theme['is_child'] ?? false,
            'parent' => $theme['parent'] ?? null,
        ];
    }

    /**
     * Update theme customizer settings
     */
    public function update(Request $request)
    {
        try {
            $activeTheme = Theme::getActiveTheme();

            if (! $activeTheme) {
                return response()->json(['error' => 'No active theme found'], 404);
            }

            // Get settings from request
            $settings = $request->all();
            unset($settings['_token']);

            // Fields that contain code and should be base64 encoded for safe JSON storage
            $codeFields = array_unique(array_merge(
                ['custom_css', 'custom_js'],
                $this->getCodeFieldKeys($activeTheme['directory'])
            ));

            // Basic sanitization and type conversion
            foreach ($settings as $key => $value) {
                if ($key === 'logo_width') {
                    $settings[$key] = $this->sanitizeLogoWidthSetting($value, 160);

                    continue;
                }

                if (is_string($value)) {
                    // Handle checkbox boolean values properly
                    if ($value === 'true') {
                        $settings[$key] = true;
                    } elseif ($value === 'false') {
                        $settings[$key] = false;
                    } elseif (in_array($key, $codeFields)) {
                        // Base64 encode code fields for safe JSON storage
                        $settings[$key] = base64_encode($value);
                    } else {
                        // Basic sanitization for text values
                        $settings[$key] = strip_tags($value);
                    }
                }
            }

            // Separate branding settings from theme settings
            $brandingKeys = $this->getBrandingKeys();
            $brandingSettings = [];
            $themeSettings = [];

            foreach ($settings as $key => $value) {
                if (isset($brandingKeys[$key])) {
                    $brandingSettings[$key] = $value;
                } else {
                    $themeSettings[$key] = $value;
                }
            }

            // Save branding settings to App Settings (database)
            $this->saveBrandingToAppSettings($brandingSettings);
            $this->syncFrontendFaviconAssets($brandingSettings);

            // Save theme-specific settings to theme options.json
            if ($themeSettings !== []) {
                $themeConfigService = new ThemeConfigService;
                $themeConfigService->setThemeOptions($activeTheme['directory'], $themeSettings);
            }

            // Clear all theme-related caches
            $this->clearThemeCache($activeTheme['directory']);

            // Dispatch job to rebuild all caches asynchronously (non-blocking)
            dispatch(new RecacheApplication('Theme customizer update: '.$activeTheme['directory']));

            $themeModel = $this->resolveThemeModelForLogging($activeTheme);
            $updatedKeys = array_keys($settings);

            $this->activity($themeModel)
                ->extra([
                    'module' => 'Theme Customizer',
                    'theme_directory' => $activeTheme['directory'] ?? null,
                    'updated_keys_sample' => array_slice($updatedKeys, 0, 10),
                    'total_keys_updated' => count($updatedKeys),
                ])
                ->updated('Theme settings updated successfully.');

            return response()->json([
                'success' => true,
                'message' => 'Theme settings updated successfully',
            ]);
        } catch (Exception $exception) {
            Log::error('Theme customizer update error: '.$exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to update theme settings: '.$exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Get live preview CSS
     */
    public function previewCSS(Request $request)
    {
        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return response('', 404);
        }

        $settings = $request->all();
        $css = $this->generateCustomCSS($activeTheme['directory'], $settings);

        return response($css)->header('Content-Type', 'text/css');
    }

    /**
     * Reset theme settings to defaults
     */
    public function reset(Request $request)
    {
        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return response()->json(['error' => 'No active theme found'], 404);
        }

        // Get default settings from merged customizer settings (Site Identity + config.json + theme functions)
        $settings = $this->getThemeCustomizerSettings($activeTheme['directory']);

        $brandingKeys = $this->getBrandingKeys();
        $brandingSettings = [];

        // Reset to defaults
        foreach ($settings['sections'] ?? [] as $sectionSettings) {
            foreach ($sectionSettings['settings'] ?? [] as $key => $setting) {
                $defaultValue = $setting['default'] ?? '';

                if (isset($brandingKeys[$key])) {
                    $brandingSettings[$key] = $defaultValue;
                } else {
                    $this->setThemeOption($activeTheme['directory'], $key, $defaultValue);
                }
            }
        }

        // Reset branding defaults to App Settings
        if ($brandingSettings !== []) {
            $this->saveBrandingToAppSettings($brandingSettings);
            $this->syncFrontendFaviconAssets($brandingSettings);
            // Note: Settings cache is automatically invalidated by SettingsObserver
        }

        // Clear all theme-related caches
        $this->clearThemeCache($activeTheme['directory']);

        // Dispatch job to rebuild all caches asynchronously (non-blocking)
        dispatch(new RecacheApplication('Theme customizer reset: '.$activeTheme['directory']));

        $this->activity($this->resolveThemeModelForLogging($activeTheme))
            ->extra([
                'module' => 'Theme Customizer',
                'theme_directory' => $activeTheme['directory'] ?? null,
            ])
            ->write(ActivityAction::UPDATE, 'Theme settings reset to defaults.');

        return response()->json([
            'success' => true,
            'message' => 'Theme settings reset to defaults',
        ]);
    }

    /**
     * Export theme settings
     */
    public function export()
    {
        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return response()->json(['error' => 'No active theme found'], 404);
        }

        $settings = $this->getCurrentValues($activeTheme['directory']);

        $filename = $activeTheme['directory'].'_settings_'.date('Y-m-d_H-i-s').'.json';

        $this->activity($this->resolveThemeModelForLogging($activeTheme))
            ->extra([
                'module' => 'Theme Customizer',
                'theme_directory' => $activeTheme['directory'] ?? null,
                'filename' => $filename,
            ])
            ->write(ActivityAction::EXPORT, 'Theme settings exported.');

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'data' => json_encode($settings, JSON_PRETTY_PRINT),
        ]);
    }

    /**
     * Import theme settings
     */
    public function import(Request $request)
    {
        $request->validate([
            'settings_file' => ['required', 'file', 'mimes:json'],
        ]);

        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return response()->json(['error' => 'No active theme found'], 404);
        }

        try {
            $content = file_get_contents($request->file('settings_file')->getRealPath());
            $settings = json_decode($content, true);

            throw_unless($settings, Exception::class, 'Invalid JSON file');

            $brandingKeys = $this->getBrandingKeys();
            $brandingSettings = [];

            // Import settings (branding to App Settings, others to options.json)
            foreach ($settings as $key => $value) {
                if (isset($brandingKeys[$key])) {
                    $brandingSettings[$key] = $value;
                } else {
                    $this->setThemeOption($activeTheme['directory'], $key, $value);
                }
            }

            if ($brandingSettings !== []) {
                $this->saveBrandingToAppSettings($brandingSettings);
                $this->syncFrontendFaviconAssets($brandingSettings);
                // Note: Settings cache is automatically invalidated by SettingsObserver
            }

            // Clear all theme-related caches
            $this->clearThemeCache($activeTheme['directory']);

            // Dispatch job to rebuild all caches asynchronously (non-blocking)
            dispatch(new RecacheApplication('Theme customizer import: '.$activeTheme['directory']));

            $this->activity($this->resolveThemeModelForLogging($activeTheme))
                ->extra([
                    'module' => 'Theme Customizer',
                    'theme_directory' => $activeTheme['directory'] ?? null,
                    'imported_keys' => array_keys($settings),
                    'source_file' => $request->file('settings_file')->getClientOriginalName(),
                ])
                ->write(ActivityAction::IMPORT, 'Theme settings imported successfully.');

            return response()->json([
                'success' => true,
                'message' => 'Theme settings imported successfully',
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'error' => 'Failed to import settings: '.$exception->getMessage(),
            ], 400);
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

    /**
     * Get Site Identity section (always injected, reads/writes to App Settings)
     */
    private function getSiteIdentitySection(): array
    {
        return [
            'title' => 'Site Identity',
            'description' => 'Site branding (shared across all themes)',
            'settings' => [
                'site_title' => [
                    'label' => 'Site Title',
                    'type' => 'text',
                    'default' => config('app.name', 'Laravel'),
                    'helper_text' => 'Used in browser tabs and as the default site name in SEO metadata.',
                ],
                'logo' => [
                    'label' => 'Logo',
                    'type' => 'image',
                    'default' => '',
                    'helper_text' => 'Use a transparent PNG or SVG for best header and footer rendering.',
                ],
                'logo_width' => [
                    'label' => 'Logo Width (px)',
                    'type' => 'number',
                    'default' => 160,
                    'helper_text' => 'Theme-level setting. Controls frontend logo width in pixels.',
                ],
                'favicon' => [
                    'label' => 'Favicon',
                    'type' => 'image',
                    'default' => '',
                    'helper_text' => 'Prefer an SVG source and use a square icon (1:1 ratio) for best cross-device output.',
                ],
                'primary_color' => [
                    'label' => 'Primary Color',
                    'type' => 'color',
                    'default' => '#252525',
                    'helper_text' => 'Used for brand accents and frontend favicon/manifest theme color.',
                ],
                'secondary_color' => [
                    'label' => 'Secondary Color',
                    'type' => 'color',
                    'default' => '#ffffff',
                    'helper_text' => 'Used for secondary UI accents in themes that support it.',
                ],
            ],
        ];
    }

    /**
     * Get theme customizer settings
     * Injects Site Identity section at the beginning (from App Settings)
     */
    private function getThemeCustomizerSettings(string $themeDirectory): array
    {
        // Start with Site Identity section (always present, syncs with App Settings)
        $sections = [
            'site_identity' => $this->getSiteIdentitySection(),
        ];

        // Get theme-specific settings from config.json with parent inheritance
        $themeConfigService = new ThemeConfigService;
        $config = $themeConfigService->getCustomizerSettings($themeDirectory);
        if (! empty($config['sections'])) {
            // Merge theme sections after Site Identity
            $sections = array_merge($sections, $config['sections']);
            $sections = $this->stripSharedColorSettingsFromThemeSections($sections);

            return ['sections' => $sections];
        }

        // Use the helper function to get settings from theme functions
        $themeSettings = theme_get_customizer_settings($themeDirectory);

        // If theme provides settings, merge them after Site Identity
        if ($themeSettings !== [] && isset($themeSettings['sections'])) {
            $sections = array_merge($sections, $themeSettings['sections']);
            $sections = $this->stripSharedColorSettingsFromThemeSections($sections);

            return ['sections' => $sections];
        }

        // Return just Site Identity if no theme settings found
        return ['sections' => $sections];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomizerSections(string $themeDirectory): array
    {
        $settings = $this->getThemeCustomizerSettings($themeDirectory);
        $sections = $settings['sections'] ?? [];

        $sections['custom_code'] = [
            'title' => 'Custom Code',
            'description' => 'Add custom CSS and JavaScript snippets for this theme.',
            'settings' => [
                'custom_css' => [
                    'label' => 'Custom CSS',
                    'type' => 'code_editor',
                    'language' => 'css',
                    'default' => '',
                    'helper_text' => 'Injected into the document head after theme styles.',
                ],
                'custom_js' => [
                    'label' => 'Custom JavaScript',
                    'type' => 'code_editor',
                    'language' => 'javascript',
                    'default' => '',
                    'helper_text' => 'Injected before the closing body tag.',
                ],
            ],
        ];

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function decodeCodeEditorValues(string $themeDirectory, array $values): array
    {
        $codeFields = array_unique(array_merge(
            ['custom_css', 'custom_js'],
            $this->getCodeFieldKeys($themeDirectory)
        ));

        foreach ($codeFields as $field) {
            $rawValue = $values[$field] ?? '';

            if (! is_string($rawValue) || $rawValue === '') {
                $values[$field] = '';

                continue;
            }

            $decoded = base64_decode($rawValue, true);
            $values[$field] = $decoded !== false ? $decoded : $rawValue;
        }

        return $values;
    }

    /**
     * Get all setting keys that should be treated as code fields.
     */
    private function getCodeFieldKeys(string $themeDirectory): array
    {
        $settings = $this->getThemeCustomizerSettings($themeDirectory);
        $codeFields = [];

        foreach ($settings['sections'] ?? [] as $section) {
            foreach ($section['settings'] ?? [] as $settingId => $setting) {
                if (($setting['type'] ?? null) === 'code_editor') {
                    $codeFields[] = $settingId;
                }
            }
        }

        return $codeFields;
    }

    /**
     * Branding keys that sync with App Settings (not stored in theme options.json)
     */
    private function getBrandingKeys(): array
    {
        return [
            'site_title' => 'site_title',   // App Settings key
            'logo' => 'site_logo',           // App Settings key (different name)
            'favicon' => 'favicon',          // App Settings key
            'primary_color' => 'branding_primary_color', // App Settings key
            'secondary_color' => 'branding_secondary_color', // App Settings key
        ];
    }

    /**
     * Save branding settings to App Settings (database)
     */
    private function saveBrandingToAppSettings(array $brandingSettings): void
    {
        $brandingKeys = $this->getBrandingKeys();
        $userId = auth()->id();

        foreach ($brandingSettings as $customizerKey => $value) {
            if (! isset($brandingKeys[$customizerKey])) {
                continue;
            }

            if ($customizerKey === 'favicon') {
                $value = $this->sanitizeFaviconSetting($value);
            }

            if ($customizerKey === 'primary_color') {
                $value = $this->sanitizeHexColorSetting($value, '#252525');
            }

            if ($customizerKey === 'secondary_color') {
                $value = $this->sanitizeHexColorSetting($value, '#ffffff');
            }

            $appSettingKey = $brandingKeys[$customizerKey];

            // Determine the group based on key
            $group = $appSettingKey === 'site_title' ? 'seo' : 'general';

            // Update or create the setting
            $setting = Settings::query()->firstOrNew([
                'group' => $group,
                'key' => $appSettingKey,
            ]);

            $setting->value = $value ?? '';

            if (empty($setting->type)) {
                $setting->type = 'string';
            }

            if (! $setting->exists && $userId) {
                $setting->created_by = $userId;
            }

            if ($userId) {
                $setting->updated_by = $userId;
            }

            $setting->save();

            // Special handling for site_title - also update .env
            if ($customizerKey === 'site_title' && ! empty($value)) {
                $settingsService = new SettingsService;
                $settingsService->updateEnvironmentVariable('APP_NAME', '"'.$value.'"');
                $settingsService->updateEnvironmentVariable('MAIL_FROM_NAME', '"'.$value.'"');
            }
        }
    }

    /**
     * Sanitize favicon setting to a safe image URL/path or empty string.
     */
    private function sanitizeFaviconSetting(mixed $value): string
    {
        $favicon = trim((string) $value);

        if ($favicon === '' || $favicon === '0') {
            return '';
        }

        $path = parse_url($favicon, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif'];

        if (! in_array($extension, $allowedExtensions, true)) {
            return '';
        }

        if (str_starts_with($favicon, '/')) {
            return $favicon;
        }

        if (! filter_var($favicon, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($favicon, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return $favicon;
    }

    /**
     * Sync generated favicon assets only when branding settings that affect them were changed.
     *
     * @param  array<string, mixed>  $brandingSettings
     */
    private function syncFrontendFaviconAssets(array $brandingSettings): void
    {
        if ($brandingSettings === []) {
            return;
        }

        $relevantKeys = [
            'favicon',
            'site_title',
            'primary_color',
            'secondary_color',
        ];

        if (array_intersect(array_keys($brandingSettings), $relevantKeys) === []) {
            return;
        }

        $this->frontendFaviconService->syncGeneratedAssets();
    }

    /**
     * Sanitize hex color setting to #RGB or #RRGGBB (lowercase), fallback on invalid.
     */
    private function sanitizeHexColorSetting(mixed $value, string $fallback): string
    {
        $color = strtolower(trim((string) $value));

        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/', $color) === 1) {
            return $color;
        }

        return $fallback;
    }

    /**
     * Sanitize logo width to a safe pixel value.
     */
    private function sanitizeLogoWidthSetting(mixed $value, int $fallback): int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (! is_numeric($value)) {
            return $fallback;
        }

        $width = (int) $value;

        return max(60, min($width, 480));
    }

    /**
     * Get current theme option values
     * Branding fields are read from App Settings, other fields from theme options.json
     */
    private function getCurrentValues(string $themeDirectory): array
    {
        // Get theme-specific options from options.json
        $optionsFile = Theme::getThemesPath().'/'.$themeDirectory.'/config/options.json';
        $options = [];

        if (file_exists($optionsFile)) {
            $content = file_get_contents($optionsFile);
            $options = json_decode($content, true) ?: [];
        }

        // Inject branding values from App Settings
        $brandingKeys = $this->getBrandingKeys();
        foreach ($brandingKeys as $customizerKey => $appSettingKey) {
            if ($customizerKey === 'primary_color') {
                $options[$customizerKey] = setting($appSettingKey, (string) ($options[$customizerKey] ?? '#252525'));

                continue;
            }

            if ($customizerKey === 'secondary_color') {
                $options[$customizerKey] = setting($appSettingKey, (string) ($options[$customizerKey] ?? '#ffffff'));

                continue;
            }

            $options[$customizerKey] = setting($appSettingKey, '');
        }

        return $options;
    }

    /**
     * Keep shared color settings in Site Identity and remove duplicates from theme sections.
     *
     * @param  array<string, mixed>  $sections
     * @return array<string, mixed>
     */
    private function stripSharedColorSettingsFromThemeSections(array $sections): array
    {
        $sharedColorKeys = ['primary_color', 'secondary_color'];

        foreach ($sections as $sectionId => &$section) {
            if ($sectionId === 'site_identity') {
                continue;
            }

            if (! isset($section['settings'])) {
                continue;
            }

            if (! is_array($section['settings'])) {
                continue;
            }

            foreach ($sharedColorKeys as $sharedColorKey) {
                unset($section['settings'][$sharedColorKey]);
            }

            if ($section['settings'] === []) {
                unset($sections[$sectionId]);
            }
        }

        unset($section);

        return $sections;
    }

    /**
     * Set theme option
     */
    private function setThemeOption(string $themeDirectory, string $key, $value): void
    {
        $optionsFile = Theme::getThemesPath().'/'.$themeDirectory.'/config/options.json';

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

        file_put_contents($optionsFile, json_encode($options, JSON_PRETTY_PRINT));

        // Clear cache using the centralized service to clear all cache tiers.
        $activeTheme = Theme::getActiveTheme();
        if ($activeTheme && $activeTheme['directory'] === $themeDirectory) {
            $this->themeOptionsCacheService->invalidateTheme($activeTheme['directory'], 'setThemeOption');
        }
    }

    /**
     * Generate custom CSS based on theme options
     */
    private function generateCustomCSS(string $themeDirectory, ?array $settings = null): string
    {
        if ($settings === null) {
            $settings = $this->getCurrentValues($themeDirectory);
        }

        $css = '';

        // Colors
        $primaryColor = $settings['primary_color'] ?? '#007cba';
        $secondaryColor = $settings['secondary_color'] ?? '#005a87';
        $accentColor = $settings['accent_color'] ?? '#ff6b6b';
        $textColor = $settings['text_color'] ?? '#333333';
        $backgroundColor = $settings['background_color'] ?? '#ffffff';

        // Typography
        $fontFamily = $settings['font_family'] ?? 'Inter';
        $fontSize = $settings['font_size'] ?? '16px';
        $lineHeight = $settings['line_height'] ?? '1.6';

        $css .= ":root {
            --primary-color: {$primaryColor};
            --secondary-color: {$secondaryColor};
            --accent-color: {$accentColor};
            --text-color: {$textColor};
            --background-color: {$backgroundColor};
            --font-primary: '{$fontFamily}', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-size-base: {$fontSize};
            --line-height-base: {$lineHeight};
        }";

        // Custom CSS
        if (isset($settings['custom_css']) && ! empty($settings['custom_css'])) {
            $css .= "\n".$settings['custom_css'];
        }

        return $css;
    }

    /**
     * Clear all theme-related caches
     */
    private function clearThemeCache(string $themeDirectory): void
    {
        $activeTheme = Theme::getActiveTheme();

        if ($activeTheme && $activeTheme['directory'] === $themeDirectory) {
            // Clear theme options cache via centralized service (memory + persistent)
            $this->themeOptionsCacheService->invalidateTheme($activeTheme['directory'], 'clearThemeCache');

            // Clear CSS caches
            Cache::forget('theme_custom_css_'.$themeDirectory);
            Cache::forget('app_theme_custom_css_v1_'.$themeDirectory);

            // Update CSS version for cache busting (linked custom.css?v={version})
            Cache::put('theme_css_version_'.$themeDirectory, time());

            // Clear customizer settings cache
            Cache::forget('theme_customizer_settings_'.$themeDirectory);

            // Clear view cache
            Artisan::call('view:clear');
        }
    }
}
