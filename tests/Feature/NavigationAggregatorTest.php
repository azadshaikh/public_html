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
}
