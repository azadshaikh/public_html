<?php

namespace Modules\CMS\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\RecacheApplication;
use App\Models\Settings;
use App\Traits\ActivityTrait;
use App\Traits\HasMediaPicker;
use BadMethodCallException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Http\Requests\UpdateSeoSettingsRequest;
use Modules\CMS\Services\SeoSettingService;
use Modules\CMS\Services\SitemapService;

/**
 * SeoSettingController - Handles SEO settings management
 *
 * This controller manages various SEO-related settings including:
 * - General SEO settings (site title, meta tags)
 * - Titles & Meta templates for different content types
 * - Local SEO settings
 * - Social Media settings
 * - Schema markup settings
 * - Sitemap configuration
 * - Robots.txt configuration
 */
class SeoSettingController extends Controller
{
    use ActivityTrait;
    use HasMediaPicker;

    // =============================================================================
    // CONSTANTS
    // =============================================================================

    private const string MODULE_NAME = 'seo';

    private const string MODULE_PATH = 'seo::settings';

    protected string $activityLogModule = 'CMS';

    // =============================================================================
    // CONSTRUCTOR & DEPENDENCY INJECTION
    // =============================================================================

    public function __construct(
        private readonly Settings $settings,
        private readonly SeoSettingService $seoSettingService
    ) {}

    // =============================================================================
    // MIDDLEWARE CONFIGURATION
    // =============================================================================

    public static function middleware(): array
    {
        // Note: SEO settings use dynamic permissions based on master_group
        // Permissions are checked in the index() and update() methods
        return [];
    }

    // =============================================================================
    // INDEX ACTION
    // =============================================================================

    /**
     * Display SEO settings page
     */
    public function index(Request $request, string $masterGroup, string $fileName): Response
    {
        // Determine permission module (cms for titlesmeta)
        // Map route master_group to actual seeded permission names
        $permissionModule = match (true) {
            $masterGroup === 'titlesmeta' => 'cms',
            $masterGroup === 'settings' && $fileName === 'titlesmeta' => 'cms',
            $masterGroup === 'common' => 'seo',
            default => $masterGroup,
        };

        // 'common' group uses 'manage_seo_settings' (no middle segment)
        $permission = $permissionModule === 'seo'
            ? 'manage_seo_settings'
            : 'manage_'.$permissionModule.'_seo_settings';

        // Check permissions
        abort_unless(Auth::user()->can($permission), 403);

        // Check if module is active for CMS-related settings
        if (in_array($masterGroup, ['cms', 'titlesmeta', 'classified']) || ($masterGroup === 'settings' && $fileName === 'titlesmeta')) {
            $moduleToCheck = $masterGroup === 'titlesmeta' || ($masterGroup === 'settings' && $fileName === 'titlesmeta') ? 'cms' : $masterGroup;
            abort_unless(active_modules($moduleToCheck), 403);
        }

        $data = $this->getViewData('settings', $masterGroup, $fileName);

        if ($masterGroup === 'integrations' && in_array($fileName, [
            'webmaster_tools',
            'google_analytics',
            'google_tags',
            'meta_pixel',
            'microsoft_clarity',
            'google_adsense',
            'other',
        ], true)) {
            return Inertia::render('cms/integrations/index', $this->getIntegrationsPageData($data['settings_data'], $fileName));
        }

        // Add sitemap status data for sitemap settings page
        if ($fileName === 'sitemap') {
            $sitemapService = resolve(SitemapService::class);
            $data['sitemapStatus'] = $sitemapService->getStatus();
        }

        $inertiaPage = $this->resolveSeoInertiaPage($request, $data, $masterGroup, $fileName);
        if ($inertiaPage !== null) {
            return Inertia::render($inertiaPage['component'], $inertiaPage['props']);
        }

        abort(404);
    }

    // =============================================================================
    // UPDATE ACTION
    // =============================================================================

    /**
     * Update SEO settings with detailed change tracking
     */
    public function update(string $masterGroup, string $fileName, UpdateSeoSettingsRequest $request): RedirectResponse
    {
        abort_unless($this->seoSettingService->validateModuleAccess($masterGroup), 403);

        try {
            // Capture old values BEFORE update for audit trail
            $oldValues = $this->captureCurrentSettings($masterGroup, $fileName, $request);

            // Perform the update
            $this->seoSettingService->updateSettings($masterGroup, $fileName, $request);

            // Dispatch job to rebuild all caches asynchronously (non-blocking)
            dispatch(new RecacheApplication(sprintf('SEO settings update: %s/%s', $masterGroup, $fileName)));

            // Get new values from the request (what was just saved)
            // Exclude internal fields that shouldn't be tracked as changes
            // Media picker components auto-generate *_url fields from *_id fields
            $excludedFields = ['_token', '_method', 'section'];

            // Exclude auto-generated media URL fields (they're generated from ID fields)
            foreach ($request->all() as $key => $value) {
                if (str_ends_with((string) $key, '_url') && $request->has(str_replace('_url', '', $key))) {
                    $excludedFields[] = $key;
                }
            }

            $newValues = $request->except($excludedFields);

            // Add boolean fields with their actual values (false if not in request)
            $booleanFields = $this->getBooleanFieldsForFile($masterGroup, $fileName);
            foreach ($booleanFields as $field) {
                if (! isset($newValues[$field])) {
                    $newValues[$field] = $request->boolean($field);
                }
            }

            // Build change summary for user feedback
            $changeSummary = $this->buildChangeSummary($masterGroup, $fileName, $oldValues, $newValues, $booleanFields);

            // Log with detailed change information
            $this->logSettingsUpdateWithChanges($masterGroup, $fileName, $oldValues, $newValues);

            if ($masterGroup === 'integrations') {
                $routeName = match ($fileName) {
                    'webmaster_tools' => 'cms.integrations.webmastertools',
                    'google_analytics' => 'cms.integrations.googleanalytics',
                    'google_tags' => 'cms.integrations.googletags',
                    'meta_pixel' => 'cms.integrations.metapixel',
                    'microsoft_clarity' => 'cms.integrations.microsoftclarity',
                    'google_adsense' => 'cms.integrations.googleadsense',
                    'other' => 'cms.integrations.other',
                    default => 'cms.integrations.index',
                };

                // Check for integration validation warnings
                $integrationWarnings = $this->seoSettingService->getIntegrationWarnings();

                if ($integrationWarnings !== []) {
                    $warningMessages = array_map(fn (array $w): string => '<strong>'.$w['field'].':</strong> '.$w['message'], $integrationWarnings);

                    return to_route($routeName)
                        ->with('success', $changeSummary)
                        ->with('error', 'Some invalid HTML was removed:<br>'.implode('<br>', $warningMessages));
                }

                return to_route($routeName)->with('success', $changeSummary);
            }

            // Preserve the section parameter when redirecting
            $redirectUrl = url()->previous();
            $section = $request->input('section', $fileName);

            // Parse the URL and preserve/add the section parameter
            $parsedUrl = parse_url($redirectUrl);
            $queryParams = [];
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
            }

            $queryParams['section'] = $section;

            $newQuery = http_build_query($queryParams);
            $finalUrl = $parsedUrl['path'].($newQuery !== '' && $newQuery !== '0' ? '?'.$newQuery : '');

            // Check for integration validation warnings
            $integrationWarnings = $this->seoSettingService->getIntegrationWarnings();

            // Add error if any invalid HTML was found (use 'error' instead of 'warning' for visibility)
            if ($integrationWarnings !== []) {
                $warningMessages = array_map(fn (array $w): string => '<strong>'.$w['field'].':</strong> '.$w['message'], $integrationWarnings);

                return redirect($finalUrl)
                    ->with('success', $changeSummary)
                    ->with('error', 'Some invalid HTML was removed:<br>'.implode('<br>', $warningMessages));
            }

            return redirect($finalUrl)->with('success', $changeSummary);
        } catch (Exception) {
            return back()
                ->with('error', 'Failed to update settings. Please try again.')
                ->withInput();
        }
    }

    // =============================================================================
    // SITEMAP REGENERATION
    // =============================================================================

    /**
     * Regenerate sitemap
     */
    public function regenerateSitemap(): RedirectResponse
    {
        $sitemapService = resolve(SitemapService::class);
        $results = $sitemapService->generateAll();

        $settingsModel = new Settings;
        $settingsModel->id = 0;

        $this->logActivity(
            $settingsModel,
            ActivityAction::UPDATE,
            'SEO Settings: Sitemap regenerated',
            [
                'module_name' => self::MODULE_NAME,
                'master_group' => 'common',
                'action_type' => 'sitemap_regeneration',
                'results' => $results,
            ]
        );

        // Calculate total URLs generated
        $totalUrls = array_sum(array_map(fn (array $r) => $r['count'] ?? 0, $results));

        return to_route('seo.settings.sitemap')
            ->with('success', sprintf('Sitemap regenerated successfully. %s URLs generated.', $totalUrls));
    }

    // =============================================================================
    // IMPORT & EXPORT ACTIONS
    // =============================================================================

    /**
     * Display the import/export page for SEO settings
     */
    public function importExport(): View|Response
    {
        // Check permissions
        abort_unless(Auth::user()->can('manage_seo_settings'), 403);

        return Inertia::render('seo/settings/import-export', [
            'seoGroups' => $this->getSeoGroups(),
        ]);
    }

    /**
     * Export all SEO settings to JSON
     */
    public function exportSeoSettings(): JsonResponse
    {
        // Check permissions
        if (! Auth::user()->can('manage_seo_settings')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access.',
            ], 403);
        }

        // Query all settings where group contains 'seo'
        $seoSettings = Settings::withoutTrashed()
            ->where('group', 'like', '%seo%')
            ->get(['group', 'key', 'value', 'type'])
            ->toArray();

        // Include robots.txt file content if it exists
        $robotsPath = public_path('/robots.txt');
        if (File::exists($robotsPath)) {
            $seoSettings[] = [
                'group' => 'seo_robots',
                'key' => 'robots_txt',
                'value' => File::get($robotsPath),
                'type' => 'string',
            ];
        }

        // Log export activity
        $settingsModel = new Settings;
        $settingsModel->id = 0;

        $this->logActivity(
            $settingsModel,
            ActivityAction::UPDATE,
            'SEO Settings: Exported '.count($seoSettings).' settings (including robots.txt)',
            [
                'module_name' => self::MODULE_NAME,
                'action_type' => 'export',
                'settings_count' => count($seoSettings),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'SEO settings exported successfully.',
            'jsondata' => json_encode($seoSettings),
        ]);
    }

    /**
     * Import SEO settings from a JSON file
     */
    public function importSeoSettings(Request $request): RedirectResponse
    {
        // Check permissions
        abort_unless(Auth::user()->can('manage_seo_settings'), 403);

        // Validate file upload
        $request->validate([
            'import_file' => ['required', 'mimes:json'],
        ], [
            'import_file.mimes' => 'Please upload a valid json file.',
        ]);

        try {
            // Read and parse JSON file
            $fileContents = File::get($request->file('import_file')->getRealPath());
            $settings = json_decode($fileContents, true) ?? [];

            if (empty($settings)) {
                return back()->with([
                    'alert-type' => 'error',
                    'message' => 'Failed to import settings. No data found in the file.',
                ]);
            }

            // Filter for SEO-related settings only
            $seoSettings = array_filter($settings, fn (array $entry): bool => isset($entry['group']) && str_contains($entry['group'], 'seo'));

            if ($seoSettings === []) {
                return back()->with([
                    'alert-type' => 'error',
                    'message' => 'No SEO settings found in the file.',
                ]);
            }

            $wasImported = false;
            $userId = Auth::id();
            $importedCount = 0;
            $updatedCount = 0;
            $createdCount = 0;

            foreach ($seoSettings as $entry) {
                if (! isset($entry['key'], $entry['value'])) {
                    continue;
                }

                // Handle robots.txt file separately
                if ($entry['key'] === 'robots_txt') {
                    $robotsPath = public_path('/robots.txt');
                    if (! empty($entry['value'])) {
                        File::put($robotsPath, $entry['value']);
                    }

                    $importedCount++;
                    $wasImported = true;

                    continue;
                }

                $data = [
                    'key' => $entry['key'],
                    'value' => $entry['value'],
                    'group' => $entry['group'] ?? null,
                    'type' => $entry['type'] ?? 'string',
                    'updated_by' => $userId,
                ];

                $existing = $this->settings->where('key', $entry['key'])->first();

                if ($existing) {
                    $existing->update($data);
                    $updatedCount++;
                    $wasImported = true;
                } else {
                    $data['created_by'] = $userId;
                    $this->settings->create($data);
                    $createdCount++;
                    $wasImported = true;
                }

                $importedCount++;
            }

            if (! $wasImported) {
                return back()->with([
                    'alert-type' => 'error',
                    'message' => 'Failed to import settings. Please check the data and try again.',
                ]);
            }

            // Note: Settings cache is automatically invalidated by SettingsObserver
            // No need to call Cache::forget('settings')

            // Clear tagged cache only if cache driver supports tags
            try {
                Cache::tags(['settings'])->flush();
            } catch (BadMethodCallException) {
                // Cache driver doesn't support tags, skip
            }

            // Log import activity
            $settingsModel = new Settings;
            $settingsModel->id = 0;

            $this->logActivity(
                $settingsModel,
                ActivityAction::UPDATE,
                sprintf('SEO Settings: Imported %d settings (%d created, %d updated)', $importedCount, $createdCount, $updatedCount),
                [
                    'module_name' => self::MODULE_NAME,
                    'action_type' => 'import',
                    'total_imported' => $importedCount,
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                ]
            );

            return back()->with([
                'alert-type' => 'success',
                'message' => sprintf('SEO settings imported successfully. %d settings processed (%d created, %d updated).', $importedCount, $createdCount, $updatedCount),
            ]);
        } catch (Exception $exception) {
            report($exception);

            return back()->with([
                'alert-type' => 'error',
                'message' => 'An error occurred while importing: '.$exception->getMessage(),
            ]);
        }
    }

    // =============================================================================
    // VIEW DATA CONFIGURATION
    // =============================================================================

    /**
     * Get view data for SEO settings pages
     */
    private function getViewData(string $action, string $masterGroup = 'seo', string $fileName = 'general'): array
    {
        $data = [
            'module_title' => __('seo::seo.seo'),
            'module_name' => __('seo::seo.seo'),
            'module_path' => self::MODULE_PATH,
            'parent_module' => __('seo::seo.seo'),
            'action' => $action,
            'page_title' => __('seo::seo.update_'.$fileName),
            'master_group' => $masterGroup,
            'file_name' => $fileName,
            'settings_data' => $this->getDecodedSettings(),
        ];

        // Set master group title
        if (in_array($masterGroup, ['cms', 'titlesmeta']) || ($masterGroup === 'settings' && $fileName === 'titlesmeta')) {
            $data['master_group_title'] = __('seo::seo.cms');
        } else {
            $data['master_group_title'] = ucwords(str_replace('_', ' ', $masterGroup));
        }

        // Add meta robots options for CMS-related settings
        if (in_array($masterGroup, ['cms', 'titlesmeta', 'classified']) || ($masterGroup === 'settings' && $fileName === 'titlesmeta')) {
            $data['meta_robots_options'] = [
                ['label' => 'index, follow', 'value' => 'index, follow'],
                ['label' => 'index, nofollow', 'value' => 'index, nofollow'],
                ['label' => 'noindex, follow', 'value' => 'noindex, follow'],
                ['label' => 'noindex, nofollow', 'value' => 'noindex, nofollow'],
            ];
        }

        // Add file-specific data
        if ($fileName === 'robots') {
            $robotsPath = public_path('/robots.txt');
            if (File::exists($robotsPath)) {
                $data['robots_txt'] = File::get($robotsPath);
            }
        } elseif ($fileName === 'local_seo') {
            $data['openingDaysArray'] = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $data['phoneNumberTypesArray'] = [
                'customer-support' => 'Customer Support',
                'technical-support' => 'Technical Support',
                'billing-support' => 'Billing Support',
            ];
            // Get business types from config and format for select component
            $businessTypes = config('cms.seo.business_types', []);
            $data['business_type_options'] = array_map(fn ($key, $item): array => [
                'value' => $key,
                'label' => $item['label'],
            ], array_keys($businessTypes), $businessTypes);
        } elseif (in_array($masterGroup, ['cms', 'titlesmeta']) || ($masterGroup === 'settings' && $fileName === 'titlesmeta')) {
            if (in_array($fileName, ['settings', 'titlesmeta'])) {
                $data['url_extentions'] = [
                    ['label' => 'None', 'value' => ''],
                    ['label' => '/', 'value' => '/'],
                    ['label' => '.html', 'value' => '.html'],
                ];
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $settingsData
     * @return array<string, mixed>
     */
    private function getIntegrationsPageData(array $settingsData, string $activeSection): array
    {
        $adsTxtPath = public_path('ads.txt');

        return [
            'activeSection' => $activeSection,
            'statuses' => [
                'webmaster_tools' => $this->resolveWebmasterToolsStatus($settingsData),
                'google_analytics' => $this->filledSetting($settingsData, 'seo_integrations_google_analytics'),
                'google_tags' => $this->filledSetting($settingsData, 'seo_integrations_google_tags'),
                'meta_pixel' => $this->filledSetting($settingsData, 'seo_integrations_meta_pixel'),
                'microsoft_clarity' => $this->filledSetting($settingsData, 'seo_integrations_ms_clarity'),
                'google_adsense' => filter_var($settingsData['seo_integrations_google_adsense_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'other' => $this->filledSetting($settingsData, 'seo_integrations_other'),
            ],
            'settings' => [
                'webmaster_tools' => [
                    'google_search_console' => (string) ($settingsData['seo_integrations_google_search_console'] ?? ''),
                    'bing_webmaster' => (string) ($settingsData['seo_integrations_bing_webmaster'] ?? ''),
                    'baidu_webmaster' => (string) ($settingsData['seo_integrations_baidu_webmaster'] ?? ''),
                    'yandex_verification' => (string) ($settingsData['seo_integrations_yandex_verification'] ?? ''),
                    'pinterest_verification' => (string) ($settingsData['seo_integrations_pinterest_verification'] ?? ''),
                    'norton_verification' => (string) ($settingsData['seo_integrations_norton_verification'] ?? ''),
                    'custom_meta_tags' => (string) ($settingsData['seo_integrations_custom_meta_tags'] ?? ''),
                ],
                'google_analytics' => [
                    'google_analytics' => (string) ($settingsData['seo_integrations_google_analytics'] ?? ''),
                ],
                'google_tags' => [
                    'google_tags' => (string) ($settingsData['seo_integrations_google_tags'] ?? ''),
                ],
                'meta_pixel' => [
                    'meta_pixel' => (string) ($settingsData['seo_integrations_meta_pixel'] ?? ''),
                ],
                'microsoft_clarity' => [
                    'ms_clarity' => (string) ($settingsData['seo_integrations_ms_clarity'] ?? ''),
                ],
                'google_adsense' => [
                    'google_adsense_enabled' => filter_var($settingsData['seo_integrations_google_adsense_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'google_adsense_code' => (string) ($settingsData['seo_integrations_google_adsense_code'] ?? ''),
                    'google_adsense_hide_for_logged_in' => filter_var($settingsData['seo_integrations_google_adsense_hide_for_logged_in'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'google_adsense_hide_on_homepage' => filter_var($settingsData['seo_integrations_google_adsense_hide_on_homepage'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'google_adsense_ads_txt' => File::exists($adsTxtPath) ? (string) File::get($adsTxtPath) : '',
                ],
                'other' => [
                    'other' => (string) ($settingsData['seo_integrations_other'] ?? ''),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{component: string, props: array<string, mixed>}|null
     */
    private function resolveSeoInertiaPage(Request $request, array $data, string $masterGroup, string $fileName): ?array
    {
        return match (true) {
            ($masterGroup === 'settings' && $fileName === 'titlesmeta') || $masterGroup === 'titlesmeta' => [
                'component' => 'seo/settings/titles-meta',
                'props' => $this->getTitlesMetaPageData($request, $data),
            ],
            $masterGroup === 'common' && $fileName === 'local_seo' => [
                'component' => 'seo/settings/local-seo',
                'props' => $this->getLocalSeoPageData($data),
            ],
            $masterGroup === 'common' && $fileName === 'social_media' => [
                'component' => 'seo/settings/social-media',
                'props' => $this->getSocialMediaPageData($data),
            ],
            $masterGroup === 'common' && $fileName === 'schema' => [
                'component' => 'seo/settings/schema',
                'props' => $this->getSchemaPageData($data),
            ],
            $masterGroup === 'common' && $fileName === 'sitemap' => [
                'component' => 'seo/settings/sitemap',
                'props' => $this->getSitemapPageData($data),
            ],
            $masterGroup === 'common' && $fileName === 'robots' => [
                'component' => 'seo/settings/robots',
                'props' => $this->getRobotsPageData($data),
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function getTitlesMetaPageData(Request $request, array $data): array
    {
        $settingsData = $data['settings_data'] ?? [];
        $activeSection = (string) $request->query('section', 'general');

        if (! in_array($activeSection, ['general', 'posts', 'pages', 'categories', 'tags', 'authors', 'search', 'error_page'], true)) {
            $activeSection = 'general';
        }

        return [
            'activeSection' => $activeSection,
            'metaRobotsOptions' => $data['meta_robots_options'] ?? [],
            'urlExtensionOptions' => $data['url_extentions'] ?? [],
            'searchEngineVisibility' => $this->toBoolean($settingsData['seo_search_engine_visibility'] ?? false),
            'generalInitialValues' => [
                'section' => 'general',
                'separator_character' => (string) ($settingsData['seo_separator_character'] ?? ''),
                'secondary_separator_character' => (string) ($settingsData['seo_secondary_separator_character'] ?? ''),
                'cms_base' => (string) ($settingsData['seo_cms_base'] ?? ''),
                'url_extension' => (string) ($settingsData['seo_url_extension'] ?? ''),
                'search_engine_visibility' => $this->toBoolean($settingsData['seo_search_engine_visibility'] ?? false),
            ],
            'sections' => [
                $this->buildTitlesMetaSectionConfig(
                    key: 'posts',
                    title: 'Posts',
                    description: 'Define URL structure, title templates, and archive crawl defaults for blog posts.',
                    helperText: 'Recommended variables: %title%, %site_title%, %separator%, %category%, %author%, and %excerpt%.',
                    settingsPrefix: 'seo_posts_',
                    supportsPermalinkBase: true,
                    supportsPermalinkStructure: true,
                    supportsMultipleCategories: true,
                    supportsPaginationIndexing: true,
                    previewPattern: 'Example: /blog/sample-post',
                ),
                $this->buildTitlesMetaSectionConfig(
                    key: 'pages',
                    title: 'Pages',
                    description: 'Set title and robots defaults for static pages and landing pages.',
                    helperText: 'Page templates usually work best with %title%, %separator%, and %site_title%.',
                    settingsPrefix: 'seo_pages_',
                    supportsPermalinkBase: false,
                    supportsPermalinkStructure: false,
                    supportsMultipleCategories: false,
                    supportsPaginationIndexing: false,
                    previewPattern: null,
                ),
                $this->buildTitlesMetaSectionConfig(
                    key: 'categories',
                    title: 'Categories',
                    description: 'Control SEO defaults for category archives and taxonomy listings.',
                    helperText: 'Category pages often benefit from %title%, %description%, and %site_title% variables.',
                    settingsPrefix: 'seo_categories_',
                    supportsPermalinkBase: true,
                    supportsPermalinkStructure: false,
                    supportsMultipleCategories: false,
                    supportsPaginationIndexing: true,
                    previewPattern: 'Example: /category/technology',
                ),
                $this->buildTitlesMetaSectionConfig(
                    key: 'tags',
                    title: 'Tags',
                    description: 'Manage tag archive prefixes, metadata templates, and pagination indexing.',
                    helperText: 'Tag archives should stay descriptive and concise to avoid thin-search snippets.',
                    settingsPrefix: 'seo_tags_',
                    supportsPermalinkBase: true,
                    supportsPermalinkStructure: false,
                    supportsMultipleCategories: false,
                    supportsPaginationIndexing: true,
                    previewPattern: 'Example: /tag/design-systems',
                ),
                $this->buildTitlesMetaSectionConfig(
                    key: 'authors',
                    title: 'Authors',
                    description: 'Define archive naming and indexing rules for author profile pages.',
                    helperText: 'Use author-aware variables when you want profile archives to rank for personal names.',
                    settingsPrefix: 'seo_authors_',
                    supportsPermalinkBase: true,
                    supportsPermalinkStructure: false,
                    supportsMultipleCategories: false,
                    supportsPaginationIndexing: true,
                    previewPattern: 'Example: /author/jane-doe',
                ),
                $this->buildTitlesMetaSectionConfig(
                    key: 'search',
                    title: 'Search',
                    description: 'Set the metadata shown on internal search results pages.',
                    helperText: 'Search pages often use noindex and include %query% to reflect the visitor search.',
                    settingsPrefix: 'seo_search_cms_',
                    supportsPermalinkBase: false,
                    supportsPermalinkStructure: false,
                    supportsMultipleCategories: false,
                    supportsPaginationIndexing: false,
                    previewPattern: null,
                ),
                $this->buildTitlesMetaSectionConfig(
                    key: 'error_page',
                    title: 'Error Pages',
                    description: 'Control how 404 pages appear in browsers and search engines.',
                    helperText: '404 pages should usually be noindex and keep their copy reassuring and short.',
                    settingsPrefix: 'seo_error_page_',
                    supportsPermalinkBase: false,
                    supportsPermalinkStructure: false,
                    supportsMultipleCategories: false,
                    supportsPaginationIndexing: false,
                    previewPattern: null,
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function getLocalSeoPageData(array $data): array
    {
        $settingsData = $data['settings_data'] ?? [];
        $logoImage = $settingsData['seo_local_seo_logo_image'] ?? null;

        return [
            'initialValues' => [
                'is_schema' => $this->toBoolean($settingsData['seo_local_seo_is_schema'] ?? false),
                'type' => (string) ($settingsData['seo_local_seo_type'] ?? 'Organization'),
                'business_type' => (string) ($settingsData['seo_local_seo_business_type'] ?? 'LocalBusiness'),
                'name' => (string) ($settingsData['seo_local_seo_name'] ?? ''),
                'description' => (string) ($settingsData['seo_local_seo_description'] ?? ''),
                'street_address' => (string) ($settingsData['seo_local_seo_street_address'] ?? ''),
                'locality' => (string) ($settingsData['seo_local_seo_locality'] ?? ''),
                'region' => (string) ($settingsData['seo_local_seo_region'] ?? ''),
                'postal_code' => (string) ($settingsData['seo_local_seo_postal_code'] ?? ''),
                'country_code' => (string) ($settingsData['seo_local_seo_country_code'] ?? ''),
                'phone' => (string) ($settingsData['seo_local_seo_phone'] ?? ''),
                'email' => (string) ($settingsData['seo_local_seo_email'] ?? ''),
                'logo_image' => is_numeric($logoImage) ? (int) $logoImage : '',
                'url' => (string) ($settingsData['seo_local_seo_url'] ?? ''),
                'is_opening_hour_24_7' => $this->toBoolean($settingsData['seo_local_seo_is_opening_hour_24_7'] ?? false),
                'opening_hour_day' => $this->decodeStringArray($settingsData['seo_local_seo_opening_hour_day'] ?? []),
                'opening_hours' => $this->decodeStringArray($settingsData['seo_local_seo_opening_hours'] ?? []),
                'closing_hours' => $this->decodeStringArray($settingsData['seo_local_seo_closing_hours'] ?? []),
                'price_range' => (string) ($settingsData['seo_local_seo_price_range'] ?? ''),
                'geo_coordinates_latitude' => (string) ($settingsData['seo_local_seo_geo_coordinates_latitude'] ?? ''),
                'geo_coordinates_longitude' => (string) ($settingsData['seo_local_seo_geo_coordinates_longitude'] ?? ''),
                'facebook_url' => (string) ($settingsData['seo_local_seo_facebook_url'] ?? ''),
                'twitter_url' => (string) ($settingsData['seo_local_seo_twitter_url'] ?? ''),
                'linkedin_url' => (string) ($settingsData['seo_local_seo_linkedin_url'] ?? ''),
                'instagram_url' => (string) ($settingsData['seo_local_seo_instagram_url'] ?? ''),
                'youtube_url' => (string) ($settingsData['seo_local_seo_youtube_url'] ?? ''),
                'founding_date' => (string) ($settingsData['seo_local_seo_founding_date'] ?? ''),
            ],
            'businessTypeOptions' => collect(config('cms.seo.business_types', []))
                ->map(fn (array $option): array => [
                    'value' => (string) ($option['value'] ?? ''),
                    'label' => (string) ($option['label'] ?? ''),
                ])
                ->all(),
            'openingDayOptions' => collect($data['openingDaysArray'] ?? [])
                ->map(fn (string $day): array => ['value' => $day, 'label' => $day])
                ->all(),
            'logoImageUrl' => ! empty($logoImage) ? get_media_url($logoImage) : null,
            ...$this->getMediaPickerProps(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function getSocialMediaPageData(array $data): array
    {
        $settingsData = $data['settings_data'] ?? [];
        $openGraphImage = $settingsData['seo_social_media_open_graph_image'] ?? null;

        return [
            'initialValues' => [
                'facebook_page_url' => (string) ($settingsData['seo_social_media_facebook_page_url'] ?? ''),
                'facebook_authorship' => (string) ($settingsData['seo_social_media_facebook_authorship'] ?? ''),
                'facebook_admin' => (string) ($settingsData['seo_social_media_facebook_admin'] ?? ''),
                'facebook_app' => (string) ($settingsData['seo_social_media_facebook_app'] ?? ''),
                'facebook_secret' => (string) ($settingsData['seo_social_media_facebook_secret'] ?? ''),
                'twitter_username' => (string) ($settingsData['seo_social_media_twitter_username'] ?? ''),
                'open_graph_image' => is_numeric($openGraphImage) ? (int) $openGraphImage : '',
                'twitter_card_type' => (string) ($settingsData['seo_social_media_twitter_card_type'] ?? 'summary_large_image'),
            ],
            'twitterCardOptions' => [
                ['value' => 'summary_large_image', 'label' => 'Summary card with large image'],
                ['value' => 'summary', 'label' => 'Summary card'],
            ],
            'openGraphImageUrl' => ! empty($openGraphImage) ? get_media_url($openGraphImage) : null,
            ...$this->getMediaPickerProps(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function getSchemaPageData(array $data): array
    {
        $settingsData = $data['settings_data'] ?? [];

        return [
            'initialValues' => [
                'enable_article_schema' => $this->toBoolean($settingsData['seo_enable_article_schema'] ?? false),
                'enable_breadcrumb_schema' => $this->toBoolean($settingsData['seo_enable_breadcrumb_schema'] ?? false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function getSitemapPageData(array $data): array
    {
        $sitemapStatus = $data['sitemapStatus'] ?? resolve(SitemapService::class)->getStatus();

        return [
            'initialValues' => [
                'enabled' => (bool) ($sitemapStatus['enabled'] ?? false),
                'posts_enabled' => (bool) ($sitemapStatus['types']['posts']['enabled'] ?? false),
                'pages_enabled' => (bool) ($sitemapStatus['types']['pages']['enabled'] ?? false),
                'categories_enabled' => (bool) ($sitemapStatus['types']['categories']['enabled'] ?? false),
                'tags_enabled' => (bool) ($sitemapStatus['types']['tags']['enabled'] ?? false),
                'authors_enabled' => (bool) ($sitemapStatus['types']['authors']['enabled'] ?? false),
                'auto_regenerate' => (bool) setting('seo.sitemap.auto_regenerate', true),
                'links_per_file' => (int) setting('seo.sitemap.links_per_file', 1000),
            ],
            'sitemapStatus' => $sitemapStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function getRobotsPageData(array $data): array
    {
        return [
            'initialValues' => [
                'robots_txt' => (string) ($data['robots_txt'] ?? ''),
            ],
            'robotsUrl' => url('/robots.txt'),
            'sitemapUrl' => url('/sitemap.xml'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTitlesMetaSectionConfig(
        string $key,
        string $title,
        string $description,
        string $helperText,
        string $settingsPrefix,
        bool $supportsPermalinkBase,
        bool $supportsPermalinkStructure,
        bool $supportsMultipleCategories,
        bool $supportsPaginationIndexing,
        ?string $previewPattern,
    ): array {
        $settingsData = $this->getDecodedSettings();

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'helperText' => $helperText,
            'supportsPermalinkBase' => $supportsPermalinkBase,
            'supportsPermalinkStructure' => $supportsPermalinkStructure,
            'supportsMultipleCategories' => $supportsMultipleCategories,
            'supportsPaginationIndexing' => $supportsPaginationIndexing,
            'previewPattern' => $previewPattern,
            'initialValues' => [
                'section' => $key,
                'permalink_base' => (string) ($settingsData[$settingsPrefix.'permalink_base'] ?? ''),
                'title_template' => (string) ($settingsData[$settingsPrefix.'title_template'] ?? ''),
                'description_template' => (string) ($settingsData[$settingsPrefix.'description_template'] ?? ''),
                'robots_default' => (string) ($settingsData[$settingsPrefix.'robots_default'] ?? ''),
                'permalink_structure' => (string) ($settingsData[$settingsPrefix.'permalink_structure'] ?? '%postname%'),
                'enable_multiple_categories' => $this->toBoolean($settingsData[$settingsPrefix.'enable_multiple_categories'] ?? false),
                'enable_pagination_indexing' => $this->toBoolean($settingsData[$settingsPrefix.'enable_pagination_indexing'] ?? false),
            ],
        ];
    }

    private function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<int, string>
     */
    private function decodeStringArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        if (! is_string($value) || trim($value) === '') {
            return [''];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [''];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $decoded));
    }

    /**
     * @param  array<string, mixed>  $settingsData
     */
    private function resolveWebmasterToolsStatus(array $settingsData): bool
    {
        foreach ([
            'seo_integrations_google_search_console',
            'seo_integrations_bing_webmaster',
            'seo_integrations_baidu_webmaster',
            'seo_integrations_yandex_verification',
            'seo_integrations_pinterest_verification',
            'seo_integrations_norton_verification',
            'seo_integrations_custom_meta_tags',
        ] as $key) {
            if ($this->filledSetting($settingsData, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $settingsData
     */
    private function filledSetting(array $settingsData, string $key): bool
    {
        $value = $settingsData[$key] ?? null;

        return is_string($value) ? trim($value) !== '' : ! empty($value);
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    /**
     * Get decoded settings data to prevent double-encoding in forms
     * For boolean settings, use raw_value to get 'true'/'false' strings
     */
    private function getDecodedSettings(): array
    {
        // Don't use cache at all for settings data to prevent stale data issues
        // Settings are already optimized with database indexing
        $settings = $this->settings::all()->mapWithKeys(function ($setting): array {
            // Create key as group_key (e.g., seo_general_search_engine_visibility)
            $key = $setting->group.'_'.$setting->key;

            // Use raw_value for booleans to get 'true'/'false' strings for forms
            $value = $setting->type === 'boolean' ? $setting->raw_value : $setting->value;

            return [$key => $value];
        })->toArray();

        // Decode HTML entities in all string values (skip booleans)
        return array_map(fn ($value): mixed => is_string($value) && ! in_array($value, ['true', 'false'])
            ? html_entity_decode($value, ENT_QUOTES, 'UTF-8')
            : $value, $settings);
    }

    /**
     * Capture current settings values for change tracking
     */
    private function captureCurrentSettings(string $masterGroup, string $fileName, UpdateSeoSettingsRequest $request): array
    {
        // Resolve the database group name (same logic as service)
        $group = $this->resolveGroupName($masterGroup, $fileName);

        // Get all relevant settings from database for this group
        $settings = $this->settings
            ->where('group', $group)
            ->pluck('value', 'key')
            ->toArray();

        // For robots.txt, capture file content
        if ($fileName === 'robots') {
            $robotsPath = public_path('/robots.txt');
            $settings['robots_txt'] = File::exists($robotsPath) ? File::get($robotsPath) : '';
        }

        // Define boolean fields per file that should always be captured
        $booleanFields = $this->getBooleanFieldsForFile($masterGroup, $fileName);

        // Define fields to exclude from change tracking
        $excludedFields = ['_token', '_method', 'section'];

        // Exclude auto-generated media URL fields
        foreach ($request->all() as $key => $value) {
            if (str_ends_with((string) $key, '_url') && $request->has(str_replace('_url', '', $key))) {
                $excludedFields[] = $key;
            }
        }

        // Capture fields from request
        $capturedFields = [];
        foreach ($request->except($excludedFields) as $key => $value) {
            // Transform key if needed (e.g., 'base' -> 'cms_base' for cms/general)
            $transformedKey = $this->transformSettingKey($masterGroup, $fileName, $key);
            $capturedFields[$key] = $settings[$transformedKey] ?? null;
        }

        // Also capture boolean fields even if not in request (unchecked = false)
        foreach ($booleanFields as $field) {
            if (! isset($capturedFields[$field])) {
                $transformedKey = $this->transformSettingKey($masterGroup, $fileName, $field);
                $capturedFields[$field] = $settings[$transformedKey] ?? null;
            }
        }

        return $capturedFields;
    }

    /**
     * Get list of boolean fields for a specific file
     */
    private function getBooleanFieldsForFile(string $masterGroup, string $fileName): array
    {
        $booleanFieldsMap = [
            'common' => [
                'general' => ['search_engine_visibility'],
                'schema' => ['is_breadcrumb'],
                'local_seo' => ['is_schema', 'is_opening_hour_24_7'],
                'sitemap' => ['enabled', 'posts_enabled', 'pages_enabled', 'categories_enabled', 'tags_enabled', 'authors_enabled', 'auto_regenerate'],
            ],
            'cms' => [
                'posts' => ['enable_multiple_categories', 'enable_pagination_indexing'],
                'pages' => [],
                'categories' => ['enable_pagination_indexing'],
                'tags' => ['enable_pagination_indexing'],
                'authors' => [],
            ],
        ];

        return $booleanFieldsMap[$masterGroup][$fileName] ?? [];
    }

    /**
     * Resolve database group name (matches service logic)
     */
    private function resolveGroupName(string $masterGroup, string $fileName): string
    {
        $map = [
            'common' => [
                'general' => 'seo',
                'local_seo' => 'seo_local_seo',
                'social_media' => 'seo_social_media',
                'schema' => 'seo',
                'sitemap' => 'seo_sitemap',
                'robots' => 'seo_robots',
            ],
            'cms' => [
                'general' => 'seo',
                'posts' => 'seo_posts',
                'pages' => 'seo_pages',
                'categories' => 'seo_categories',
                'tags' => 'seo_tags',
                'authors' => 'seo_authors',
                'search' => 'seo_search',
                'ads' => 'seo_ads',
                'error_page' => 'seo_error_page',
            ],
            'classified' => [
                'general' => 'seo',
                'ads' => 'seo_ads',
                'users' => 'seo_users',
                'search' => 'seo_search',
            ],
            'integrations' => [
                'google_analytics' => 'seo_integrations',
                'google_tags' => 'seo_integrations',
                'microsoft_clarity' => 'seo_integrations',
                'meta_pixel' => 'seo_integrations',
                'other' => 'seo_integrations',
            ],
        ];

        return $map[$masterGroup][$fileName] ?? 'seo_'.$fileName;
    }

    /**
     * Transform setting key if needed (matches service logic)
     */
    private function transformSettingKey(string $masterGroup, string $fileName, string $key): string
    {
        $keyMap = [
            'cms' => [
                'general' => [
                    'base' => 'cms_base',
                ],
            ],
            'classified' => [
                'general' => [
                    'base' => 'classified_base',
                ],
            ],
        ];

        return $keyMap[$masterGroup][$fileName][$key] ?? $key;
    }

    /**
     * Log settings update with detailed change information
     */
    private function logSettingsUpdateWithChanges(
        string $masterGroup,
        string $fileName,
        array $oldValues,
        array $newValues
    ): void {
        $settingsModel = new Settings;
        $settingsModel->id = 0;

        // Build friendly setting name based on folder structure
        $settingDisplayName = $this->getFriendlySettingName($fileName);
        $groupDisplayName = $this->getFriendlyGroupName($masterGroup);

        // Get list of boolean fields for proper comparison
        $booleanFields = $this->getBooleanFieldsForFile($masterGroup, $fileName);

        // Determine which fields actually changed (normalize for comparison)
        $changedFields = [];
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            // Check if this is a boolean field
            $isBooleanField = in_array($key, $booleanFields);

            // Normalize values for comparison (handle null, empty strings, booleans)
            $normalizedOld = $this->normalizeValue($oldValue, $isBooleanField);
            $normalizedNew = $this->normalizeValue($newValue, $isBooleanField);

            if ($normalizedOld !== $normalizedNew) {
                $fieldLabel = ucwords(str_replace('_', ' ', $key));
                $changedFields[] = $fieldLabel;

                // Format display values (convert booleans to true/false strings)
                $displayOldValue = $isBooleanField ? $this->formatBooleanForDisplay($oldValue) : $oldValue;
                $displayNewValue = $isBooleanField ? $this->formatBooleanForDisplay($newValue) : $newValue;

                $changes[$key] = [
                    'old' => $displayOldValue,
                    'new' => $displayNewValue,
                ];
            }
        }

        // Build descriptive log message
        $changeCount = count($changedFields);
        if ($changeCount > 0) {
            $fieldList = $changeCount <= 3
                ? implode(', ', $changedFields)
                : implode(', ', array_slice($changedFields, 0, 3)).' and '.($changeCount - 3).' more';

            $message = sprintf('SEO Settings Updated: %s (%d field', $settingDisplayName, $changeCount).($changeCount > 1 ? 's' : '').sprintf(' changed: %s)', $fieldList);
        } else {
            $message = sprintf('SEO Settings Saved: %s (no changes detected)', $settingDisplayName);
        }

        // Log with previous values for full audit trail
        $this->logActivityWithPreviousValues(
            $settingsModel,
            ActivityAction::UPDATE,
            $message,
            $oldValues,
            [
                'module_name' => self::MODULE_NAME,
                'master_group' => $masterGroup,
                'file_name' => $fileName,
                'group_display_name' => $groupDisplayName,
                'setting_display_name' => $settingDisplayName,
                'changed_fields' => $changedFields,
                'change_count' => $changeCount,
                'new_values' => $newValues,
                'changes' => $changes,
            ]
        );
    }

    /**
     * Normalize value for comparison (handle different data types)
     */
    private function normalizeValue($value, bool $isBooleanField = false): string
    {
        // For boolean fields, treat null as false (unchecked state)
        if ($isBooleanField && is_null($value)) {
            return '0';
        }

        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        // Convert string representations of booleans to consistent format
        $stringValue = (string) $value;
        if (in_array(strtolower($stringValue), ['true', 'false'], true)) {
            return strtolower($stringValue) === 'true' ? '1' : '0';
        }

        return $stringValue;
    }

    /**
     * Format boolean value for display in activity logs
     */
    private function formatBooleanForDisplay($value): bool
    {
        // Handle null as false
        if (is_null($value)) {
            return false;
        }

        // Handle actual boolean
        if (is_bool($value)) {
            return $value;
        }

        // Handle string representations
        $stringValue = (string) $value;

        // Check for truthy values: '1', 'true', 'yes', 'on'
        // Everything else is false
        return in_array(strtolower($stringValue), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Get friendly group name for display (follows folder structure)
     */
    private function getFriendlyGroupName(string $masterGroup): string
    {
        $groupNames = [
            'common' => 'SEO',
            'cms' => 'CMS',
            'classified' => 'Classified',
            'settings' => 'Settings',
        ];

        return $groupNames[$masterGroup] ?? ucwords(str_replace('_', ' ', $masterGroup));
    }

    /**
     * Get friendly setting name for display (follows folder structure)
     */
    private function getFriendlySettingName(string $fileName): string
    {
        // Special mappings for titlesmeta files
        $titleMetaFiles = [
            'posts' => 'Posts',
            'pages' => 'Pages',
            'categories' => 'Categories',
            'tags' => 'Tags',
            'authors' => 'Authors',
            'search' => 'Search',
            'error_page' => 'Error Page',
        ];

        // Check if it's a titlesmeta file
        if (isset($titleMetaFiles[$fileName])) {
            return 'Titles & Meta / '.$titleMetaFiles[$fileName];
        }

        // Standard file name mappings
        $fileNames = [
            'general' => 'General',
            'local_seo' => 'Local SEO',
            'social_media' => 'Social Media',
            'schema' => 'Schema',
            'sitemap' => 'Sitemap',
            'robots' => 'Robots',
            'googleanalytics' => 'Google Analytics',
            'googletags' => 'Google Tags',
        ];

        return $fileNames[$fileName] ?? ucwords(str_replace('_', ' ', $fileName));
    }

    /**
     * Build change summary for user feedback message
     */
    private function buildChangeSummary(string $masterGroup, string $fileName, array $oldValues, array $newValues, array $booleanFields): array
    {
        $changedFields = [];
        $changes = [];

        // Determine if this is an integration-type setting (contains scripts/HTML)
        // For these, we don't want to show the actual values as they would mess up the frontend
        $isIntegrationSetting = $masterGroup === 'integrations';

        // Fields that contain scripts, HTML, or long code that shouldn't be displayed in success messages
        $scriptFields = [
            'google_analytics',  // Google Analytics
            'google_tags',       // Google Tag Manager
            'ms_clarity',        // Microsoft Clarity
            'meta_pixel',        // Meta Pixel
            'other',             // Other integrations
            'robots_txt',        // Robots.txt content
        ];        // Field label mappings for cleaner display
        $fieldLabels = [
            // General settings
            'site_title' => 'Site Title',
            'separator_character' => 'Separator Character',
            'secondary_separator_character' => 'Secondary Separator Character',
            'search_engine_visibility' => 'Search Engine Visibility',

            // Posts, Pages, Categories, Tags, Authors - Consistent naming
            'permalink_base' => 'Permalink Base',
            'permalink_structure' => 'Permalink Structure',
            'url_extension' => 'URL Extension',
            'title_template' => 'Title Template',
            'description_template' => 'Description Template',
            'robots_default' => 'Default Robots Meta',
            'enable_multiple_categories' => 'Allow Multiple Categories',
            'enable_pagination_indexing' => 'Enable Pagination Indexing',

            // Social media settings
            'facebook_page_url' => 'Facebook Page URL',
            'facebook_authorship' => 'Facebook Authorship',
            'facebook_admin' => 'Facebook Admin ID',
            'facebook_app' => 'Facebook App ID',
            'facebook_secret' => 'Facebook App Secret',
            'twitter_username' => 'X (Twitter) Username',
            'open_graph_image' => 'Open Graph Image',
            'twitter_card_type' => 'X (Twitter) Card Type',

            // Schema settings
            'enable_breadcrumb_schema' => 'Breadcrumb Schema',
            // Local SEO settings
            'is_schema' => 'Local SEO Schema',
            'type' => 'Organization Type',
            'business_type' => 'Business Type',
            'name' => 'Business Name',
            'street_address' => 'Street Address',
            'locality' => 'City/Locality',
            'region' => 'State/Region',
            'postal_code' => 'Postal Code',
            'country_code' => 'Country',
            'phone' => 'Phone Number',
            'email' => 'Email Address',
            'logo_image' => 'Logo Image',
            'url' => 'Website URL',
            'is_opening_hour_24_7' => '24/7 Operation',
            'opening_hours_format' => 'Hours Format',
            'price_range' => 'Price Range',
            'google_maps_api_key' => 'Google Maps API Key',
            'geo_coordinates_latitude' => 'Latitude',
            'geo_coordinates_longitude' => 'Longitude',

            // Sitemap settings
            'enable_sitemap' => 'Enable Sitemap',
            'enable_sitemap_indexes' => 'Enable Sitemap Indexes',
            'links_per_sitemap' => 'Links Per Sitemap',
            'enable_category_sitemap' => 'Enable Category Sitemap',
            'enable_tag_sitemap' => 'Enable Tag Sitemap',
            'enable_author_sitemap' => 'Enable Author Sitemap',

            // Integration settings
            'google_analytics' => 'Google Analytics Tracking Script',
            'google_tags' => 'Google Tag Manager Script',
            'ms_clarity' => 'Microsoft Clarity Script',
            'meta_pixel' => 'Meta Pixel Code',
            'other' => 'Custom Tags',
            'robots_txt' => 'Robots.txt Content',
        ];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            $isBooleanField = in_array($key, $booleanFields);
            $isScriptField = in_array($key, $scriptFields);

            // Normalize values for comparison
            $normalizedOld = $this->normalizeValue($oldValue, $isBooleanField);
            $normalizedNew = $this->normalizeValue($newValue, $isBooleanField);

            if ($normalizedOld !== $normalizedNew) {
                $fieldLabel = $fieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key));

                // Format display values
                $displayOldValue = $isBooleanField ? $this->formatBooleanForDisplay($oldValue) : $oldValue;
                $displayNewValue = $isBooleanField ? $this->formatBooleanForDisplay($newValue) : $newValue;

                // For boolean fields, show enabled/disabled
                if ($isBooleanField) {
                    $oldLabel = $displayOldValue ? 'enabled' : 'disabled';
                    $newLabel = $displayNewValue ? 'enabled' : 'disabled';
                    $changedFields[] = sprintf('%s: %s → %s', $fieldLabel, $oldLabel, $newLabel);
                } elseif ($isScriptField || $isIntegrationSetting) {
                    // For script/integration fields, don't show the actual values
                    // Just indicate that the field was updated
                    $changedFields[] = $fieldLabel.' updated';
                } else {
                    // Handle array values (like opening hours, phone numbers)
                    if (is_array($oldValue)) {
                        $oldDisplay = '('.count($oldValue).' items)';
                    } else {
                        $oldDisplay = $oldValue ? (strlen((string) $oldValue) > 30 ? substr((string) $oldValue, 0, 30).'...' : $oldValue) : '(empty)';
                    }

                    if (is_array($newValue)) {
                        $newDisplay = '('.count($newValue).' items)';
                    } else {
                        $newDisplay = $newValue ? (strlen((string) $newValue) > 30 ? substr((string) $newValue, 0, 30).'...' : $newValue) : '(empty)';
                    }

                    $changedFields[] = sprintf('%s: "%s" → "%s"', $fieldLabel, $oldDisplay, $newDisplay);
                }

                $changes[$key] = [
                    'field' => $fieldLabel,
                    'old' => $displayOldValue,
                    'new' => $displayNewValue,
                ];
            }
        }

        $changeCount = count($changedFields);
        $settingName = $this->getFriendlySettingName($fileName);

        if ($changeCount > 0) {
            return [
                'title' => 'Success!',
                'message' => $settingName.' settings updated successfully.',
                'changes' => $changes,
                'count' => $changeCount,
            ];
        }

        return [
            'title' => 'Success!',
            'message' => $settingName.' settings saved successfully (no changes detected).',
            'changes' => [],
            'count' => 0,
        ];
    }

    /**
     * Get list of SEO groups for display
     */
    private function getSeoGroups(): array
    {
        return Settings::withoutTrashed()
            ->where('group', 'like', '%seo%')
            ->distinct()
            ->pluck('group')
            ->sort()
            ->values()
            ->toArray();
    }
}
