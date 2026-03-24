<?php

declare(strict_types=1);

namespace Modules\Agency\Tests\Feature;

use Tests\TestCase;

class AgencyModuleMigrationTest extends TestCase
{
    public function test_agency_navigation_uses_inline_svg_icons_instead_of_legacy_remix_icon_keys(): void
    {
        $contents = file_get_contents(base_path('modules/Agency/config/navigation.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("'sections' => [", $contents);
        $this->assertStringContainsString("'customer_portal' => [", $contents);
        $this->assertStringContainsString("'admin' => [", $contents);
        $this->assertStringContainsString("'<svg viewBox=", $contents);
        $this->assertStringNotContainsString("'icon' => 'ri-", $contents);
    }
}
