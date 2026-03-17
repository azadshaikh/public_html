<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Unit;

use Tests\TestCase;

class ThemeServiceNoRuntimeFaviconInjectionTest extends TestCase
{
    public function test_theme_service_does_not_include_frontend_runtime_favicon_injection(): void
    {
        $path = base_path('modules/CMS/app/Services/ThemeService.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/CMS/app/Services/ThemeService.php');
        $this->assertStringNotContainsString('applyFaviconTags', $contents);
        $this->assertStringNotContainsString('stripExistingFaviconTags', $contents);
        $this->assertStringNotContainsString('FrontendFaviconService', $contents);
        $this->assertStringNotContainsString('cms-auto-favicon', $contents);
    }
}
