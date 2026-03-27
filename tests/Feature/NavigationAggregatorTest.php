<?php

namespace Tests\Feature;

use App\Helpers\NavigationAggregator;
use Tests\TestCase;

class NavigationAggregatorTest extends TestCase
{
    public function test_navigation_payload_includes_quick_open_metadata_and_sidebar_visibility(): void
    {
        config()->set('navigation.cache_enabled', false);
        config()->set('navigation.sections', [
            'test' => [
                'label' => 'Test',
                'weight' => 10,
                'area' => 'top',
                'show_label' => true,
                'items' => [
                    'dashboard' => [
                        'label' => 'Dashboard',
                        'route' => 'dashboard',
                        'active_patterns' => ['dashboard'],
                        'sidebar_visible' => false,
                        'quick_open' => [
                            'priority' => 150,
                            'description' => 'Open the main dashboard',
                            'aliases' => ['Home'],
                            'keywords' => ['go home'],
                        ],
                    ],
                ],
            ],
        ]);

        $navigation = NavigationAggregator::getUnifiedByArea();
        $section = collect($navigation['top'])->firstWhere('key', 'test');

        $this->assertNotNull($section);
        $this->assertCount(1, $section['items']);
        $this->assertSame(route('dashboard'), $section['items'][0]['url']);
        $this->assertFalse($section['items'][0]['sidebar_visible']);
        $this->assertSame(150, $section['items'][0]['quick_open']['priority']);
        $this->assertSame(
            'Open the main dashboard',
            $section['items'][0]['quick_open']['description'],
        );
        $this->assertSame(['Home'], $section['items'][0]['quick_open']['aliases']);
        $this->assertSame(['go home'], $section['items'][0]['quick_open']['keywords']);
    }

    public function test_navigation_payload_defaults_quick_open_metadata_when_unspecified(): void
    {
        config()->set('navigation.cache_enabled', false);
        config()->set('navigation.sections', [
            'test' => [
                'label' => 'Test',
                'weight' => 10,
                'area' => 'top',
                'show_label' => true,
                'items' => [
                    'dashboard' => [
                        'label' => 'Dashboard',
                        'route' => 'dashboard',
                        'active_patterns' => ['dashboard'],
                    ],
                ],
            ],
        ]);

        $navigation = NavigationAggregator::getUnifiedByArea();
        $section = collect($navigation['top'])->firstWhere('key', 'test');

        $this->assertNotNull($section);
        $this->assertTrue($section['items'][0]['sidebar_visible']);
        $this->assertSame(
            [
                'enabled' => true,
                'priority' => 0,
                'description' => null,
                'aliases' => [],
                'keywords' => [],
            ],
            $section['items'][0]['quick_open'],
        );
    }

    public function test_application_and_cms_navigation_configs_define_search_terms_for_quick_open(): void
    {
        $appNavigation = include config_path('navigation.php');
        $cmsNavigation = include base_path('modules/CMS/config/navigation.php');
        $releaseManagerNavigation = include base_path('modules/ReleaseManager/config/navigation.php');

        $this->assertSame(
            ['Members', 'Team'],
            $appNavigation['sections']['manage']['items']['users']['quick_open']['aliases'],
        );
        $this->assertContains(
            'webmaster tools',
            $appNavigation['sections']['manage']['items']['seo_integrations']['quick_open']['keywords'],
        );
        $this->assertContains(
            'background jobs',
            $appNavigation['sections']['masters']['items']['laravel_jobs']['quick_open']['keywords'],
        );

        $this->assertSame(
            ['Blog Posts', 'Articles'],
            $cmsNavigation['sections']['cms']['items']['cms_posts']['children']['cms_posts']['quick_open']['aliases'],
        );
        $this->assertContains(
            'navigation builder',
            $cmsNavigation['sections']['appearance']['items']['cms_menus']['quick_open']['keywords'],
        );
        $this->assertFalse(
            $cmsNavigation['sections']['appearance']['items']['cms_theme_customizer']['sidebar_visible'],
        );
        $this->assertSame(
            'cms.appearance.themes.customizer.index',
            $cmsNavigation['sections']['appearance']['items']['cms_theme_customizer']['route'],
        );
        $this->assertContains(
            'theme customizer',
            $cmsNavigation['sections']['appearance']['items']['cms_theme_customizer']['quick_open']['keywords'],
        );
        $this->assertContains(
            'create website page',
            $cmsNavigation['sections']['cms']['items']['cms_pages_create']['quick_open']['keywords'],
        );

        $this->assertSame('cms', $releaseManagerNavigation['sections']['releasemanager']['area']);
        $this->assertGreaterThan(
            $appNavigation['sections']['dashboard']['weight'],
            $releaseManagerNavigation['sections']['releasemanager']['weight'],
        );
        $this->assertLessThan(
            $appNavigation['sections']['manage']['weight'],
            $releaseManagerNavigation['sections']['releasemanager']['weight'],
        );
    }
}
