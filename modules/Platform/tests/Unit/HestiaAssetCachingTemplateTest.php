<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class HestiaAssetCachingTemplateTest extends TestCase
{
    public function test_astero_active_template_caches_vite_build_assets_aggressively(): void
    {
        $contents = file_get_contents(base_path('hestia/data/templates/web/nginx/php-fpm/astero-active.stpl'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('location ~* ^/build/assets/', $contents);
        $this->assertStringContainsString('public, max-age=31536000, s-maxage=31536000, immutable', $contents);
        $this->assertStringContainsString('public, max-age=2592000, s-maxage=2592000', $contents);
    }

    public function test_laravel_template_caches_vite_build_assets_aggressively(): void
    {
        $contents = file_get_contents(base_path('hestia/data/templates/web/nginx/php-fpm/laravel.stpl'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('location ~* ^/build/assets/', $contents);
        $this->assertStringContainsString('public, max-age=31536000, s-maxage=31536000, immutable', $contents);
        $this->assertStringContainsString('public, max-age=2592000, s-maxage=2592000', $contents);
    }
}
