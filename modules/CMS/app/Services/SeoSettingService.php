<?php

namespace Modules\CMS\Services;

use App\Models\Settings;
use App\Services\SettingsService;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Http\Requests\UpdateSeoSettingsRequest;

class SeoSettingService
{
    /**
     * Allowed HTML tags in <head> section
     */
    protected const ALLOWED_HEAD_TAGS = ['meta', 'script', 'style', 'link', 'noscript'];

    /**
     * Track whether actual changes were made during an update operation.
     */
    private bool $hasActualChanges = false;

    /**
     * Store validation warnings for integration content
     */
    private array $integrationWarnings = [];

    public function __construct(
        private readonly Settings $settings,
        private readonly MenuUrlService $menuUrlService
    ) {}

    /**
     * Get any warnings from the last update operation
     */
    public function getIntegrationWarnings(): array
    {
        return $this->integrationWarnings;
    }

    public function updateSettings(string $masterGroup, string $fileName, UpdateSeoSettingsRequest $request): bool
    {
        // Reset warnings for this operation
        $this->integrationWarnings = [];

        // Exclude fields that shouldn't be saved as settings
        // - _token: Laravel CSRF token
        // - section: Frontend navigation state (used to remember active tab)
        // - *_url: Media picker generates URL fields (only save the ID)
        // - *_id: Media picker generates duplicate ID fields (only save the main field)
        $excludedFields = ['_token', 'section'];

        // Exclude auto-generated media picker fields (_url and _id suffixes)
        foreach ($request->all() as $key => $value) {
            if (str_ends_with((string) $key, '_url') || str_ends_with((string) $key, '_id')) {
                $baseField = str_replace(['_url', '_id'], '', $key);
                // Only exclude if the base field exists (this is a generated field)
                if ($request->has($baseField)) {
                    $excludedFields[] = $key;
                }
            }
        }

        $dataInput = $request->except($excludedFields);

        DB::beginTransaction();
        try {
            $result = $this->processSettingsUpdate($masterGroup, $fileName, $dataInput, $request);

            DB::commit();

            // Move post-update actions outside of transaction to prevent timeouts
            // Only run post-update actions if there were actual changes
            if ($result && $this->hasActualChanges) {
                $this->performPostUpdateActions($masterGroup, $fileName);
            }

            return $result;
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function regenerateSitemap(): void
    {
        Log::info('regenerate sitemap called');

        $sitemapService = resolve(SitemapService::class);
        $sitemapService->generateAll();
    }

    public function updateMenuItemUrls(): void
    {
        $this->menuUrlService->updateMenuItemUrls();
    }

    public function validateModuleAccess(string $masterGroup): bool
    {
        if (in_array($masterGroup, ['cms', 'classified'])) {
            return active_modules($masterGroup);
        }

        // Special case: titlesmeta settings require CMS module
        if ($masterGroup === 'settings') {
            return active_modules('cms');
        }

        return true;
    }

    private function processSettingsUpdate(string $masterGroup, string $fileName, array $dataInput, UpdateSeoSettingsRequest $request): bool
    {
        if ($fileName === 'robots') {
            return $this->updateRobotsFile($dataInput);
        }

        return $this->updateSettingsData($masterGroup, $fileName, $dataInput, $request);
    }

    private function updateRobotsFile(array $dataInput): bool
    {
        $filepath = public_path('/robots.txt');
        $this->hasActualChanges = false;

        if (isset($dataInput['robots_txt']) && ! empty($dataInput['robots_txt'])) {
            $currentContent = File::exists($filepath) ? File::get($filepath) : '';

            if ($currentContent !== $dataInput['robots_txt']) {
                if (File::exists($filepath)) {
                    File::replace($filepath, $dataInput['robots_txt']);
                } else {
                    File::put($filepath, $dataInput['robots_txt']);
                }

                $this->hasActualChanges = true;
            }
        } elseif (File::exists($filepath)) {
            File::delete($filepath);
            $this->hasActualChanges = true;
        }

        return true;
    }

    private function updateSettingsData(string $masterGroup, string $fileName, array $dataInput, UpdateSeoSettingsRequest $request): bool
    {
        $processedData = $this->processSettingsDataByGroup($masterGroup, $fileName, $dataInput, $request);

        return $this->saveSettingsToDatabase($masterGroup, $fileName, $processedData);
    }

    private function processSettingsDataByGroup(string $masterGroup, string $fileName, array $dataInput, UpdateSeoSettingsRequest $request): array
    {
        if ($masterGroup === 'common') {
            return $this->processCommonSettings($fileName, $dataInput, $request);
        }

        if ($masterGroup === 'cms' || $masterGroup === 'titlesmeta' || ($masterGroup === 'settings' && $fileName === 'titlesmeta')) {
            return $this->processCmsSettings($fileName, $dataInput, $request);
        }

        if ($masterGroup === 'integrations') {
            return $this->processIntegrationsSettings($fileName, $dataInput, $request);
        }

        return $dataInput;
    }

    private function processIntegrationsSettings(string $fileName, array $dataInput, UpdateSeoSettingsRequest $request): array
    {
        // Validate HTML content for all integration fields
        $dataInput = $this->validateIntegrationContent($dataInput, $fileName);

        if ($fileName === 'google_adsense') {
            // Handle ads.txt file
            if ($request->has('google_adsense_ads_txt')) {
                $adsContent = $request->input('google_adsense_ads_txt', '');
                $adsFilePath = public_path('ads.txt');

                if (! empty($adsContent)) {
                    File::put($adsFilePath, $adsContent);
                } elseif (File::exists($adsFilePath)) {
                    File::delete($adsFilePath);
                }

                // Remove from dataInput as it's handled separately (file, not database)
                unset($dataInput['google_adsense_ads_txt']);
            }

            // Process boolean fields
            $dataInput['google_adsense_enabled'] = $request->boolean('google_adsense_enabled') ? 'true' : 'false';
            $dataInput['google_adsense_hide_for_logged_in'] = $request->boolean('google_adsense_hide_for_logged_in') ? 'true' : 'false';
            $dataInput['google_adsense_hide_on_homepage'] = $request->boolean('google_adsense_hide_on_homepage') ? 'true' : 'false';
        }

        return $dataInput;
    }

    /**
     * Validate integration content to ensure only valid <head> elements are used.
     * Invalid content is stripped and warnings are logged.
     */
    private function validateIntegrationContent(array $dataInput, string $fileName): array
    {
        // Fields that should contain only valid head tags
        $htmlFields = $this->getHtmlFieldsForIntegration($fileName);

        foreach ($htmlFields as $field => $label) {
            if (! isset($dataInput[$field])) {
                continue;
            }

            if (in_array(trim((string) $dataInput[$field]), ['', '0'], true)) {
                continue;
            }

            $originalContent = trim($dataInput[$field]);
            $sanitizedContent = $this->sanitizeHeadContent($originalContent);

            // Check if content was modified (meaning invalid tags were present)
            if ($originalContent !== $sanitizedContent) {
                $invalidTags = $this->findInvalidTags($originalContent);

                if ($invalidTags !== []) {
                    $this->integrationWarnings[] = [
                        'field' => $label,
                        'message' => 'Invalid HTML tags were removed: '.implode(', ', array_unique($invalidTags)).'. Only &lt;meta&gt;, &lt;script&gt;, &lt;style&gt;, &lt;link&gt;, and &lt;noscript&gt; tags are allowed in the head section.',
                    ];
                }

                // Use sanitized content
                $dataInput[$field] = $sanitizedContent;
            }
        }

        return $dataInput;
    }

    /**
     * Get HTML fields for a specific integration section
     */
    private function getHtmlFieldsForIntegration(string $fileName): array
    {
        $fieldMap = [
            'google_analytics' => ['google_analytics' => 'Google Analytics'],
            'google_tags' => ['google_tags' => 'Google Tags'],
            'microsoft_clarity' => ['ms_clarity' => 'Microsoft Clarity'],
            'meta_pixel' => ['meta_pixel' => 'Meta Pixel'],
            'other' => ['other' => 'Other Scripts'],
            'webmaster_tools' => [
                'google_search_console' => 'Google Search Console',
                'bing_webmaster' => 'Bing Webmaster',
                'baidu_webmaster' => 'Baidu Webmaster',
                'yandex_verification' => 'Yandex Verification',
                'pinterest_verification' => 'Pinterest Verification',
                'norton_verification' => 'Norton Verification',
                'custom_meta_tags' => 'Custom Meta Tags',
            ],
            'google_adsense' => ['google_adsense_code' => 'Google AdSense Code'],
        ];

        return $fieldMap[$fileName] ?? [];
    }

    /**
     * Sanitize HTML content to only include valid <head> tags.
     */
    private function sanitizeHeadContent(string $content): string
    {
        $content = trim($content);
        if ($content === '' || $content === '0') {
            return '';
        }

        // Build regex pattern for allowed tags
        $allowedTagsPattern = implode('|', self::ALLOWED_HEAD_TAGS);

        // Match only allowed tags (opening, closing, and self-closing)
        $pattern = '/<('.$allowedTagsPattern.')(\s[^>]*)?\s*\/?>(?:.*?<\/\1>)?/is';

        preg_match_all($pattern, $content, $matches);

        if (empty($matches[0])) {
            return '';
        }

        return implode("\n", $matches[0]);
    }

    /**
     * Find invalid HTML tags in content
     */
    private function findInvalidTags(string $content): array
    {
        $invalidTags = [];

        // Match all HTML tags
        preg_match_all('/<([a-zA-Z][a-zA-Z0-9]*)[^>]*>/i', $content, $matches);

        foreach ($matches[1] as $tag) {
            $tagLower = strtolower($tag);
            if (! in_array($tagLower, self::ALLOWED_HEAD_TAGS)) {
                $invalidTags[] = '&lt;'.htmlspecialchars($tag).'&gt;';
            }
        }

        return $invalidTags;
    }

    private function processCommonSettings(string $fileName, array $dataInput, UpdateSeoSettingsRequest $request): array
    {
        switch ($fileName) {
            case 'sitemap':
                return $this->processSitemapSettings($request);

            case 'general':
                return $this->processGeneralSettings($dataInput, $request);

            case 'schema':
                $dataInput['enable_article_schema'] = $request->boolean('enable_article_schema');
                $dataInput['enable_breadcrumb_schema'] = $request->boolean('enable_breadcrumb_schema');
                break;

            case 'local_seo':
                return $this->processLocalSeoSettings($dataInput, $request);

            case 'social_media':
                return $this->processSocialMediaSettings($dataInput, $request);
        }

        return $dataInput;
    }

    /**
     * Process sitemap settings with dot notation keys.
     * Saves settings with 'seo.sitemap.' prefix for consistency.
     */
    private function processSitemapSettings(UpdateSeoSettingsRequest $request): array
    {
        $sitemapSettings = [
            'seo.sitemap.enabled' => $request->boolean('enabled'),
            'seo.sitemap.posts_enabled' => $request->boolean('posts_enabled'),
            'seo.sitemap.pages_enabled' => $request->boolean('pages_enabled'),
            'seo.sitemap.categories_enabled' => $request->boolean('categories_enabled'),
            'seo.sitemap.tags_enabled' => $request->boolean('tags_enabled'),
            'seo.sitemap.authors_enabled' => $request->boolean('authors_enabled'),
            'seo.sitemap.auto_regenerate' => $request->boolean('auto_regenerate'),
            'seo.sitemap.links_per_file' => max(100, min(50000, $request->integer('links_per_file', 1000))),
        ];

        foreach ($sitemapSettings as $key => $value) {
            $this->settings->updateOrCreate(
                ['group' => 'seo', 'key' => $key],
                ['value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value]
            );
        }

        // Note: Settings cache is automatically invalidated by SettingsObserver

        // Mark that we have actual changes
        $this->hasActualChanges = true;

        // Return empty array since we've already saved the settings
        return [];
    }

    private function processGeneralSettings(array $dataInput, UpdateSeoSettingsRequest $request): array
    {
        // search_engine_visibility: ON (true) = visible to search engines, OFF (false) = hidden from search engines
        // Default is false (hidden) if not set
        $dataInput['search_engine_visibility'] = $request->boolean('search_engine_visibility') ? 'true' : 'false';

        if ($request->has('site_title')) {
            $app_name = config('app.name');
            if ($request->site_title !== $app_name) {
                $settings_service = new SettingsService;
                $settings_service->updateEnvironmentVariable('APP_NAME', '"'.$request->site_title.'"');
                $settings_service->updateEnvironmentVariable('MAIL_FROM_NAME', '"'.$request->site_title.'"');
            }

            // Save site title to database settings table (accessible via setting() function)
            $this->settings->updateOrCreate(
                ['group' => 'seo', 'key' => 'site_title'],
                ['value' => $request->site_title]
            );

            // Remove from dataInput as it's handled separately
            unset($dataInput['site_title']);
        }

        return $dataInput;
    }

    private function processLocalSeoSettings(array $dataInput, UpdateSeoSettingsRequest $request): array
    {
        $dataInput['is_schema'] = $request->boolean('is_schema');
        $dataInput['opening_hour_day'] = json_encode($request->input('opening_hour_day', []));
        $dataInput['opening_hours'] = json_encode($request->input('opening_hours', []));
        $dataInput['closing_hours'] = json_encode($request->input('closing_hours', []));
        $dataInput['phone_number_type'] = json_encode($request->input('phone_number_type', []));
        $dataInput['phone_number'] = json_encode($request->input('phone_number', []));
        $dataInput['is_opening_hour_24_7'] = $request->boolean('is_opening_hour_24_7');

        if ($request->has('logo_image')) {
            unset($dataInput['logo_image_id'], $dataInput['logo_image_url']);
        }

        return $dataInput;
    }

    private function processSocialMediaSettings(array $dataInput, UpdateSeoSettingsRequest $request): array
    {
        if ($request->has('open_graph_image')) {
            unset($dataInput['open_graph_image_id'], $dataInput['open_graph_image_url']);
        }

        return $dataInput;
    }

    private function processCmsSettings(string $fileName, array $dataInput, UpdateSeoSettingsRequest $request): array
    {
        switch ($fileName) {
            case 'general':
                $dataInput['cms_base'] = (empty($request->input('cms_base')) ? '' : strtolower((string) $request->input('cms_base')));
                break;
            case 'posts':
                $dataInput['permalink_base'] = (empty($request->input('permalink_base')) ? '' : strtolower((string) $request->input('permalink_base')));
                $dataInput['enable_multiple_categories'] = $request->boolean('enable_multiple_categories');
                $dataInput['enable_pagination_indexing'] = $request->boolean('enable_pagination_indexing');
                break;
            case 'categories':
            case 'authors':
            case 'tags':
                $dataInput['permalink_base'] = (empty($request->input('permalink_base')) ? '' : strtolower((string) $request->input('permalink_base')));
                $dataInput['enable_pagination_indexing'] = $request->boolean('enable_pagination_indexing');
                break;
        }

        return $dataInput;
    }

    private function saveSettingsToDatabase(string $masterGroup, string $fileName, array $dataInput): bool
    {
        $this->hasActualChanges = false;

        foreach ($dataInput as $key => $value) {
            [$group, $resolvedKey] = $this->resolveSettingGroupAndKey($masterGroup, $fileName, $key);
            $setting = $this->settings->where('group', $group)->where('key', $resolvedKey)->first();

            // Normalize empty values to empty string for consistent comparison
            $normalizedValue = $value === null ? '' : (is_array($value) ? json_encode($value) : (string) $value);

            if ($setting) {
                // Only update if value has actually changed
                if ($setting->value !== $normalizedValue) {
                    if (! in_array($normalizedValue, ['', '0', false], true)) {
                        $setting->update([
                            'value' => $normalizedValue,
                            'updated_by' => auth()->id(),
                        ]);
                    } else {
                        // Update to empty string instead of deleting
                        // This preserves the setting record and prevents data loss
                        $setting->update([
                            'value' => '',
                            'updated_by' => auth()->id(),
                        ]);
                    }

                    $this->hasActualChanges = true;
                }
            } elseif (! in_array($normalizedValue, ['', '0', false], true)) {
                // Only create new settings if value is non-empty
                $this->settings->create([
                    'group' => $group,
                    'key' => $resolvedKey,
                    'value' => $normalizedValue,
                    'type' => $this->determineSettingType($value),
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                $this->hasActualChanges = true;
            }
        }

        // Return true for successful operation (even if no changes were needed)
        return true;
    }

    private function resolveSettingGroupAndKey(string $masterGroup, string $fileName, string $key): array
    {
        $group = $this->resolveGroupName($masterGroup, $fileName);
        $resolvedKey = $this->transformSettingKey($masterGroup, $fileName, $key);

        return [$group, $resolvedKey];
    }

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
                'classified_user' => 'seo_classified_user',
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
                'webmaster_tools' => 'seo_integrations',
                'other' => 'seo_integrations',
                'google_adsense' => 'seo_integrations',
            ],
        ];

        return $map[$masterGroup][$fileName] ?? 'seo_'.$fileName;
    }

    private function transformSettingKey(string $masterGroup, string $fileName, string $key): string
    {
        $keyMap = [
            'cms' => [
                'general' => [
                    'cms_base' => 'cms_base',
                ],
                'search' => [
                    'title_template' => 'cms_title_template',
                    'description_template' => 'cms_description_template',
                    'robots_default' => 'cms_robots_default',
                ],
                'ads' => [
                    'title_template' => 'cms_title_template',
                    'description_template' => 'cms_description_template',
                    'robots_default' => 'cms_robots_default',
                ],
            ],
            'classified' => [
                'general' => [
                    'permalink_base' => 'classified_base',
                ],
                'search' => [
                    'title_template' => 'classified_title_template',
                    'description_template' => 'classified_description_template',
                    'robots_default' => 'classified_robots_default',
                ],
                'ads' => [
                    'title_template' => 'classified_title_template',
                    'description_template' => 'classified_description_template',
                    'robots_default' => 'classified_robots_default',
                ],
                'users' => [
                    'permalink_base' => 'user_base',
                ],
            ],
        ];

        return $keyMap[$masterGroup][$fileName][$key] ?? $key;
    }

    /**
     * Determine the appropriate type for a setting value
     */
    private function determineSettingType($value): string
    {
        if (is_bool($value) || $value === 'true' || $value === 'false' || $value === '1' || $value === '0') {
            return 'boolean';
        }

        if (is_numeric($value) && ! str_contains($value, '.')) {
            return 'integer';
        }

        if (is_numeric($value) && str_contains($value, '.')) {
            return 'float';
        }

        if (is_array($value)) {
            return 'array';
        }

        // Check if it's JSON
        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return 'json';
            }
        }

        return 'string';
    }

    private function performPostUpdateActions(string $masterGroup, string $fileName): void
    {
        try {
            // Note: Settings cache is automatically invalidated by SettingsObserver
            // No need to call Cache::forget('settings')

            // Clear config cache only if needed (for environment variable changes)
            if ($masterGroup === 'common' && $fileName === 'general') {
                Artisan::call('config:clear');
            }

            // Regenerate sitemap if sitemap settings or cms/classified settings are updated
            if ($this->shouldRegenerateSitemap($masterGroup, $fileName)) {
                $this->regenerateSitemap();
            }

            // Update menu item URLs when CMS settings are updated
            if ($this->shouldUpdateMenuUrls($masterGroup, $fileName)) {
                $this->updateMenuItemUrls();
            }
        } catch (Exception $exception) {
            // Log the error but don't break the main flow
            Log::error('Error in post-update actions: '.$exception->getMessage(), [
                'masterGroup' => $masterGroup,
                'fileName' => $fileName,
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function shouldRegenerateSitemap(string $masterGroup, string $fileName): bool
    {
        return $fileName === 'sitemap' || in_array($masterGroup, ['cms', 'titlesmeta', 'classified']) || ($masterGroup === 'settings' && $fileName === 'titlesmeta');
    }

    private function shouldUpdateMenuUrls(string $masterGroup, string $fileName): bool
    {
        // Update menu URLs when CMS permalink structures or general/page/category/tag/post settings are updated
        return (($masterGroup === 'cms' || $masterGroup === 'titlesmeta') && ! in_array($fileName, ['search', 'error_page'])) ||
               ($masterGroup === 'settings' && $fileName === 'titlesmeta' && ! in_array($fileName, ['search', 'error_page']));
    }
}
