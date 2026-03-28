<?php

namespace Modules\CMS\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Modules\CMS\Services\SitemapService;

trait InteractsWithSeoSettingsPages
{
    /**
     * Get view data for SEO settings pages
     *
     * @return array<string, mixed>
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

        if (in_array($masterGroup, ['cms', 'titlesmeta']) || ($masterGroup === 'settings' && $fileName === 'titlesmeta')) {
            $data['master_group_title'] = __('seo::seo.cms');
        } else {
            $data['master_group_title'] = ucwords(str_replace('_', ' ', $masterGroup));
        }

        if (in_array($masterGroup, ['cms', 'titlesmeta', 'classified']) || ($masterGroup === 'settings' && $fileName === 'titlesmeta')) {
            $data['meta_robots_options'] = [
                ['label' => 'index, follow', 'value' => 'index, follow'],
                ['label' => 'index, nofollow', 'value' => 'index, nofollow'],
                ['label' => 'noindex, follow', 'value' => 'noindex, follow'],
                ['label' => 'noindex, nofollow', 'value' => 'noindex, nofollow'],
            ];
        }

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

    /**
     * Get decoded settings data to prevent double-encoding in forms
     * For boolean settings, use raw_value to get 'true'/'false' strings
     *
     * @return array<string, mixed>
     */
    private function getDecodedSettings(): array
    {
        $settings = $this->settings::all()->mapWithKeys(function ($setting): array {
            $key = $setting->group.'_'.$setting->key;
            $value = $setting->type === 'boolean' ? $setting->raw_value : $setting->value;

            return [$key => $value];
        })->toArray();

        return array_map(fn ($value): mixed => is_string($value) && ! in_array($value, ['true', 'false'])
            ? html_entity_decode($value, ENT_QUOTES, 'UTF-8')
            : $value, $settings);
    }
}
