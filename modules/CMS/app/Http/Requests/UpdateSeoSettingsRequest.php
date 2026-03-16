<?php

namespace Modules\CMS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeoSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $masterGroup = $this->route('master_group');

        // Map route master_group to actual seeded permission names
        $permissionModule = match ($masterGroup) {
            'common' => 'seo',
            'settings', 'titlesmeta' => 'cms',
            default => $masterGroup,
        };

        // 'common' group uses 'manage_seo_settings' (no middle segment)
        $permission = $permissionModule === 'seo'
            ? 'manage_seo_settings'
            : 'manage_'.$permissionModule.'_seo_settings';

        return $this->user()->can($permission);
    }

    public function rules(): array
    {
        $masterGroup = $this->route('master_group');
        $fileName = $this->route('file_name');

        $rules = [];

        // Common validation rules
        if ($fileName === 'robots') {
            $rules['robots_txt'] = 'nullable|string';
        }

        if ($masterGroup === 'common') {
            $rules = array_merge($rules, $this->getCommonRules($fileName));
        } elseif ($masterGroup === 'cms') {
            $rules = array_merge($rules, $this->getCmsRules($fileName));
        } elseif ($masterGroup === 'classified') {
            $rules = array_merge($rules, $this->getClassifiedRules());
        } elseif ($masterGroup === 'integrations') {
            $rules = array_merge($rules, $this->getIntegrationRules($fileName));
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            // Social media
            'facebook_page_url.url' => 'Please enter a valid Facebook Page URL.',
            'facebook_authorship.url' => 'Please enter a valid Facebook profile URL.',
            'twitter_username.regex' => 'X (Twitter) username must be 1-15 characters and may only contain letters, numbers, and underscores.',
            'twitter_card_type.in' => 'Please select a valid X (Twitter) card type.',
            'open_graph_image.integer' => 'Please select a valid image from the media library.',
            'open_graph_image.exists' => 'The selected image does not exist in the media library.',
            // General
            'site_title.required' => 'Site title is required.',
            // Local SEO
            'type.in' => 'Please select either Person or Organization.',
            'email.email' => 'Please enter a valid email address.',
            'url.url' => 'Please enter a valid website URL.',
            'phone.max' => 'Phone number must not exceed 50 characters.',
            'geo_coordinates_latitude.between' => 'Latitude must be between -90 and 90 degrees.',
            'geo_coordinates_longitude.between' => 'Longitude must be between -180 and 180 degrees.',
            'opening_hour_day.*.in' => 'Please select a valid day of the week.',
            'opening_hours_format.in' => 'Please select either 12hr or 24hr format.',
            // Sitemap
            'links_per_sitemap.integer' => 'Links per sitemap must be a valid number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert checkbox values to boolean
        $booleanFields = [
            'enable', 'enable_indexes', 'enable_category', 'enable_tag', 'enable_author',
            'search_engine_visibility', 'is_breadcrumb', 'is_schema', 'is_opening_hour_24_7',
            'enable_multiple_categories', 'enable_pagination_indexing',
        ];

        $data = [];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->boolean($field);
            }
        }

        $this->merge($data);
    }

    private function getCommonRules(string $fileName): array
    {
        return match ($fileName) {
            'sitemap' => [
                'enable' => 'nullable|boolean',
                'enable_indexes' => 'nullable|boolean',
                'links_per_sitemap' => [
                    'nullable',
                    'integer',
                    function ($attribute, $value, $fail): void {
                        // Only require min:1 if enable_indexes is enabled
                        if ($this->boolean('enable_indexes') && $value < 1) {
                            $fail('Links per sitemap must be at least 1 when sitemap indexes are enabled.');
                        }
                    },
                ],
                'enable_category' => 'nullable|boolean',
                'enable_tag' => 'nullable|boolean',
                'enable_author' => 'nullable|boolean',
            ],
            'general' => [
                'separator_character' => 'nullable|string|max:10',
                'secondary_separator_character' => 'nullable|string|max:10',
                'cms_base' => 'nullable|string|max:255',
                'url_extension' => 'nullable|string|in:,/,.html',
                'search_engine_visibility' => 'nullable|boolean',
            ],
            'schema' => [
                'is_breadcrumb' => 'nullable|boolean',
            ],
            'local_seo' => [
                'is_schema' => 'nullable|boolean',
                'type' => 'nullable|in:Person,Organization',
                'business_type' => 'nullable|string|max:255',
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'street_address' => 'nullable|string|max:255',
                'locality' => 'nullable|string|max:100',
                'region' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'country_name' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'logo_image' => 'nullable|integer',
                'url' => 'nullable|url|max:255',
                'is_opening_hour_24_7' => 'nullable|boolean',
                'opening_hour_day' => 'nullable|array',
                'opening_hour_day.*' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'opening_hours' => 'nullable|array',
                'opening_hours.*' => 'nullable|string|max:10',
                'closing_hours' => 'nullable|array',
                'closing_hours.*' => 'nullable|string|max:10',
                'opening_hours_format' => 'nullable|in:12hr,24hr',
                'phone_number_type' => 'nullable|array',
                'phone_number_type.*' => 'nullable|string',
                'phone_number' => 'nullable|array',
                'phone_number.*' => 'nullable|string|max:50',
                'price_range' => 'nullable|string|max:50',
                'google_maps_api_key' => 'nullable|string|max:255',
                'geo_coordinates_latitude' => 'nullable|numeric|between:-90,90',
                'geo_coordinates_longitude' => 'nullable|numeric|between:-180,180',
                // Social Media URLs
                'facebook_url' => 'nullable|url|max:255',
                'twitter_url' => 'nullable|url|max:255',
                'linkedin_url' => 'nullable|url|max:255',
                'instagram_url' => 'nullable|url|max:255',
                'youtube_url' => 'nullable|url|max:255',
                // Additional info
                'founding_date' => 'nullable|date',
                'currencies_accepted' => 'nullable|string|max:255',
                'payment_accepted' => 'nullable|string|max:255',
            ],
            'social_media' => [
                'facebook_page_url' => 'nullable|url|max:255',
                'facebook_authorship' => 'nullable|url|max:255',
                'facebook_admin' => 'nullable|string|max:255',
                'facebook_app' => 'nullable|string|max:255',
                'facebook_secret' => 'nullable|string|max:255',
                'twitter_username' => 'nullable|string|max:255|regex:/^@?[A-Za-z0-9_]{1,15}$/',
                'open_graph_image' => 'nullable|integer|exists:media,id',
                'twitter_card_type' => 'nullable|in:summary_large_image,summary',
            ],
            default => [],
        };
    }

    private function getCmsRules(string $fileName): array
    {
        return match ($fileName) {
            'posts' => [
                'enable_multiple_categories' => 'nullable|boolean',
                'enable_pagination_indexing' => 'nullable|boolean',
            ],
            'categories', 'authors', 'tags' => [
                'enable_pagination_indexing' => 'nullable|boolean',
            ],
            'settings' => [
                'url_extension' => 'nullable|string|in:,/,.html',
            ],
            default => [],
        };
    }

    private function getClassifiedRules(): array
    {
        // Add specific validation rules for classified module if needed
        return [];
    }

    private function getIntegrationRules(string $fileName): array
    {
        $rules = [];

        foreach ($this->getIntegrationHtmlFields($fileName) as $field) {
            $rules[$field] = ['nullable', 'string'];
        }

        return $rules;
    }

    private function getIntegrationHtmlFields(string $fileName): array
    {
        $fieldMap = [
            'google_analytics' => ['google_analytics'],
            'google_tags' => ['google_tags'],
            'microsoft_clarity' => ['ms_clarity'],
            'meta_pixel' => ['meta_pixel'],
            'other' => ['other'],
            'webmaster_tools' => [
                'google_search_console',
                'bing_webmaster',
                'baidu_webmaster',
                'yandex_verification',
                'pinterest_verification',
                'norton_verification',
                'custom_meta_tags',
            ],
            'google_adsense' => ['google_adsense_code'],
        ];

        return $fieldMap[$fileName] ?? [];
    }
}
