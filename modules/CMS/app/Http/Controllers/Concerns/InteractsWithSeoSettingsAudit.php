<?php

namespace Modules\CMS\Http\Controllers\Concerns;

use App\Enums\ActivityAction;
use App\Models\Settings;
use Illuminate\Support\Facades\File;
use Modules\CMS\Http\Requests\UpdateSeoSettingsRequest;

trait InteractsWithSeoSettingsAudit
{
    /**
     * Capture current settings values for change tracking
     *
     * @return array<string, mixed>
     */
    private function captureCurrentSettings(string $masterGroup, string $fileName, UpdateSeoSettingsRequest $request): array
    {
        $group = $this->resolveGroupName($masterGroup, $fileName);

        $settings = $this->settings
            ->where('group', $group)
            ->pluck('value', 'key')
            ->toArray();

        if ($fileName === 'robots') {
            $robotsPath = public_path('/robots.txt');
            $settings['robots_txt'] = File::exists($robotsPath) ? File::get($robotsPath) : '';
        }

        $booleanFields = $this->getBooleanFieldsForFile($masterGroup, $fileName);
        $excludedFields = ['_token', '_method', 'section'];

        foreach ($request->all() as $key => $value) {
            if (str_ends_with((string) $key, '_url') && $request->has(str_replace('_url', '', $key))) {
                $excludedFields[] = $key;
            }
        }

        $capturedFields = [];
        foreach ($request->except($excludedFields) as $key => $value) {
            $transformedKey = $this->transformSettingKey($masterGroup, $fileName, $key);
            $capturedFields[$key] = $settings[$transformedKey] ?? null;
        }

        foreach ($booleanFields as $field) {
            if (! isset($capturedFields[$field])) {
                $transformedKey = $this->transformSettingKey($masterGroup, $fileName, $field);
                $capturedFields[$field] = $settings[$transformedKey] ?? null;
            }
        }

        return $capturedFields;
    }

    /**
     * @return array<int, string>
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
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function logSettingsUpdateWithChanges(
        string $masterGroup,
        string $fileName,
        array $oldValues,
        array $newValues,
    ): void {
        $settingsModel = new Settings;
        $settingsModel->id = 0;

        $settingDisplayName = $this->getFriendlySettingName($fileName);
        $groupDisplayName = $this->getFriendlyGroupName($masterGroup);
        $booleanFields = $this->getBooleanFieldsForFile($masterGroup, $fileName);
        $changedFields = [];
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            $isBooleanField = in_array($key, $booleanFields);
            $normalizedOld = $this->normalizeValue($oldValue, $isBooleanField);
            $normalizedNew = $this->normalizeValue($newValue, $isBooleanField);

            if ($normalizedOld !== $normalizedNew) {
                $fieldLabel = ucwords(str_replace('_', ' ', $key));
                $changedFields[] = $fieldLabel;

                $displayOldValue = $isBooleanField ? $this->formatBooleanForDisplay($oldValue) : $oldValue;
                $displayNewValue = $isBooleanField ? $this->formatBooleanForDisplay($newValue) : $newValue;

                $changes[$key] = [
                    'old' => $displayOldValue,
                    'new' => $displayNewValue,
                ];
            }
        }

        $changeCount = count($changedFields);
        if ($changeCount > 0) {
            $fieldList = $changeCount <= 3
                ? implode(', ', $changedFields)
                : implode(', ', array_slice($changedFields, 0, 3)).' and '.($changeCount - 3).' more';

            $message = sprintf('SEO Settings Updated: %s (%d field', $settingDisplayName, $changeCount).($changeCount > 1 ? 's' : '').sprintf(' changed: %s)', $fieldList);
        } else {
            $message = sprintf('SEO Settings Saved: %s (no changes detected)', $settingDisplayName);
        }

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

    private function normalizeValue(mixed $value, bool $isBooleanField = false): string
    {
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
            return json_encode($value) ?: '[]';
        }

        $stringValue = (string) $value;
        if (in_array(strtolower($stringValue), ['true', 'false'], true)) {
            return strtolower($stringValue) === 'true' ? '1' : '0';
        }

        return $stringValue;
    }

    private function formatBooleanForDisplay(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        $stringValue = (string) $value;

        return in_array(strtolower($stringValue), ['1', 'true', 'yes', 'on'], true);
    }

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

    private function getFriendlySettingName(string $fileName): string
    {
        $titleMetaFiles = [
            'posts' => 'Posts',
            'pages' => 'Pages',
            'categories' => 'Categories',
            'tags' => 'Tags',
            'authors' => 'Authors',
            'search' => 'Search',
            'error_page' => 'Error Page',
        ];

        if (isset($titleMetaFiles[$fileName])) {
            return 'Titles & Meta / '.$titleMetaFiles[$fileName];
        }

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
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<int, string>  $booleanFields
     * @return array<string, mixed>
     */
    private function buildChangeSummary(string $masterGroup, string $fileName, array $oldValues, array $newValues, array $booleanFields): array
    {
        $changedFields = [];
        $changes = [];
        $isIntegrationSetting = $masterGroup === 'integrations';

        $scriptFields = [
            'google_analytics',
            'google_tags',
            'ms_clarity',
            'meta_pixel',
            'other',
            'robots_txt',
        ];

        $fieldLabels = [
            'site_title' => 'Site Title',
            'separator_character' => 'Separator Character',
            'secondary_separator_character' => 'Secondary Separator Character',
            'search_engine_visibility' => 'Search Engine Visibility',
            'permalink_base' => 'Permalink Base',
            'permalink_structure' => 'Permalink Structure',
            'url_extension' => 'URL Extension',
            'title_template' => 'Title Template',
            'description_template' => 'Description Template',
            'robots_default' => 'Default Robots Meta',
            'enable_multiple_categories' => 'Allow Multiple Categories',
            'enable_pagination_indexing' => 'Enable Pagination Indexing',
            'facebook_page_url' => 'Facebook Page URL',
            'facebook_authorship' => 'Facebook Authorship',
            'facebook_admin' => 'Facebook Admin ID',
            'facebook_app' => 'Facebook App ID',
            'facebook_secret' => 'Facebook App Secret',
            'twitter_username' => 'X (Twitter) Username',
            'open_graph_image' => 'Open Graph Image',
            'twitter_card_type' => 'X (Twitter) Card Type',
            'enable_breadcrumb_schema' => 'Breadcrumb Schema',
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
            'enable_sitemap' => 'Enable Sitemap',
            'enable_sitemap_indexes' => 'Enable Sitemap Indexes',
            'links_per_sitemap' => 'Links Per Sitemap',
            'enable_category_sitemap' => 'Enable Category Sitemap',
            'enable_tag_sitemap' => 'Enable Tag Sitemap',
            'enable_author_sitemap' => 'Enable Author Sitemap',
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
            $normalizedOld = $this->normalizeValue($oldValue, $isBooleanField);
            $normalizedNew = $this->normalizeValue($newValue, $isBooleanField);

            if ($normalizedOld !== $normalizedNew) {
                $fieldLabel = $fieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
                $displayOldValue = $isBooleanField ? $this->formatBooleanForDisplay($oldValue) : $oldValue;
                $displayNewValue = $isBooleanField ? $this->formatBooleanForDisplay($newValue) : $newValue;

                if ($isBooleanField) {
                    $oldLabel = $displayOldValue ? 'enabled' : 'disabled';
                    $newLabel = $displayNewValue ? 'enabled' : 'disabled';
                    $changedFields[] = sprintf('%s: %s → %s', $fieldLabel, $oldLabel, $newLabel);
                } elseif ($isScriptField || $isIntegrationSetting) {
                    $changedFields[] = $fieldLabel.' updated';
                } else {
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
}
