<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BladeMigrationCleanupTest extends TestCase
{
    public function test_legacy_page_header_component_has_been_removed(): void
    {
        $this->assertFileDoesNotExist(base_path('resources/views/components/page-header.blade.php'));
    }

    public function test_platform_and_ai_registry_no_longer_ship_page_header_blade_pages(): void
    {
        foreach ([
            base_path('modules/CMS/resources/views'),
            base_path('modules/Platform/resources/views'),
            base_path('modules/AIRegistry/resources/views'),
        ] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                $contents = $file->getContents();

                $this->assertStringNotContainsString(
                    'x-page-header',
                    $contents,
                    sprintf('Legacy Blade header still present in %s', $file->getPathname()),
                );
                $this->assertStringNotContainsString(
                    'x-auth-layout',
                    $contents,
                    sprintf('Legacy Blade auth layout still present in %s', $file->getPathname()),
                );
                $this->assertStringNotContainsString(
                    'x-form-elements.',
                    $contents,
                    sprintf('Legacy Blade form component still present in %s', $file->getPathname()),
                );
            }
        }
    }
}
