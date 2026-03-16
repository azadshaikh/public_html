<?php

namespace Modules\CMS\Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SeoSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedDefaultSeoSettings();
    }

    /**
     * Seed default SEO settings with the updated group names.
     */
    private function seedDefaultSeoSettings(): void
    {
        $settings = [
            'seo' => [
                ['key' => 'separator_character', 'value' => '|', 'type' => 'string'],
                ['key' => 'secondary_separator_character', 'value' => '-', 'type' => 'string'],
                ['key' => 'search_engine_visibility', 'value' => false, 'type' => 'boolean'],
                ['key' => 'seo.sitemap.enabled', 'value' => true, 'type' => 'boolean'],
                ['key' => 'seo.sitemap.posts_enabled', 'value' => true, 'type' => 'boolean'],
                ['key' => 'seo.sitemap.pages_enabled', 'value' => true, 'type' => 'boolean'],
                ['key' => 'seo.sitemap.categories_enabled', 'value' => true, 'type' => 'boolean'],
                ['key' => 'seo.sitemap.tags_enabled', 'value' => true, 'type' => 'boolean'],
                ['key' => 'seo.sitemap.authors_enabled', 'value' => true, 'type' => 'boolean'],
                ['key' => 'seo.sitemap.auto_regenerate', 'value' => true, 'type' => 'boolean'],
                ['key' => 'seo.sitemap.links_per_file', 'value' => 1000, 'type' => 'integer'],
                ['key' => 'enable_article_schema', 'value' => true, 'type' => 'boolean'],
                ['key' => 'enable_breadcrumb_schema', 'value' => true, 'type' => 'boolean'],
            ],
            'seo_posts' => [
                ['key' => 'permalink_base', 'value' => '', 'type' => 'string'],
                ['key' => 'title_template', 'value' => '%title% %separator% %site_title%', 'type' => 'string'],
                ['key' => 'description_template', 'value' => '%excerpt%', 'type' => 'string'],
                ['key' => 'robots_default', 'value' => 'index, follow', 'type' => 'string'],
                ['key' => 'permalink_structure', 'value' => '%category%/%postname%', 'type' => 'string'],
                ['key' => 'enable_multiple_categories', 'value' => false, 'type' => 'boolean'],
                ['key' => 'enable_pagination_indexing', 'value' => false, 'type' => 'boolean'],
            ],
            'seo_pages' => [
                ['key' => 'title_template', 'value' => '%title% %separator% %site_title%', 'type' => 'string'],
                ['key' => 'description_template', 'value' => '%excerpt%', 'type' => 'string'],
                ['key' => 'robots_default', 'value' => 'index, follow', 'type' => 'string'],
            ],
            'seo_categories' => [
                ['key' => 'permalink_base', 'value' => '', 'type' => 'string'],
                ['key' => 'title_template', 'value' => '%title% %separator% %site_title%', 'type' => 'string'],
                ['key' => 'description_template', 'value' => '%excerpt%', 'type' => 'string'],
                ['key' => 'robots_default', 'value' => 'index, follow', 'type' => 'string'],
                ['key' => 'enable_pagination_indexing', 'value' => false, 'type' => 'boolean'],
            ],
            'seo_tags' => [
                ['key' => 'permalink_base', 'value' => 'tag', 'type' => 'string'],
                ['key' => 'title_template', 'value' => '%title% %separator% %site_title%', 'type' => 'string'],
                ['key' => 'description_template', 'value' => '%excerpt%', 'type' => 'string'],
                ['key' => 'robots_default', 'value' => 'index, follow', 'type' => 'string'],
                ['key' => 'enable_pagination_indexing', 'value' => false, 'type' => 'boolean'],
            ],
            'seo_authors' => [
                ['key' => 'permalink_base', 'value' => 'author', 'type' => 'string'],
                ['key' => 'title_template', 'value' => '%title% %separator% %site_title%', 'type' => 'string'],
                ['key' => 'description_template', 'value' => '%user_bio%', 'type' => 'string'],
                ['key' => 'robots_default', 'value' => 'index, follow', 'type' => 'string'],
                ['key' => 'enable_pagination_indexing', 'value' => false, 'type' => 'boolean'],
            ],
            'seo_search' => [
                ['key' => 'cms_title_template', 'value' => '%title% %separator% %site_title%', 'type' => 'string'],
                ['key' => 'cms_description_template', 'value' => '', 'type' => 'string'],
                ['key' => 'cms_robots_default', 'value' => 'noindex, nofollow', 'type' => 'string'],
            ],
            'seo_error_page' => [
                ['key' => 'title_template', 'value' => 'Page Not Found %separator% %site_title%', 'type' => 'string'],
                ['key' => 'description_template', 'value' => 'Sorry, the page you were looking for could not be found. It may have been moved, deleted, or never existed.', 'type' => 'string'],
                ['key' => 'robots_default', 'value' => 'noindex, nofollow', 'type' => 'string'],
            ],
            'seo_integrations' => [
                ['key' => 'google_analytics', 'value' => '', 'type' => 'string'],
                ['key' => 'google_tags', 'value' => '', 'type' => 'string'],
                ['key' => 'microsoft_clarity', 'value' => '', 'type' => 'string'],
                ['key' => 'meta_pixel', 'value' => '', 'type' => 'string'],
                ['key' => 'google_search_console', 'value' => '', 'type' => 'string'],
                ['key' => 'bing_webmaster', 'value' => '', 'type' => 'string'],
                ['key' => 'baidu_webmaster', 'value' => '', 'type' => 'string'],
                ['key' => 'yandex_verification', 'value' => '', 'type' => 'string'],
                ['key' => 'pinterest_verification', 'value' => '', 'type' => 'string'],
                ['key' => 'norton_verification', 'value' => '', 'type' => 'string'],
                ['key' => 'custom_meta_tags', 'value' => '', 'type' => 'string'],
                ['key' => 'other', 'value' => '', 'type' => 'string'],
            ],
        ];

        foreach ($settings as $group => $groupSettings) {
            foreach ($groupSettings as $settingData) {
                Settings::query()->updateOrCreate([
                    'group' => $group,
                    'key' => $settingData['key'],
                ], [
                    'value' => $settingData['value'],
                    'type' => $settingData['type'],
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
            }
        }
    }
}
