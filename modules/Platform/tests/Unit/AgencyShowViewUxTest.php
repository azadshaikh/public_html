<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class AgencyShowViewUxTest extends TestCase
{
    public function test_agency_show_view_uses_command_center_layout_and_section_tabs(): void
    {
        $path = base_path('modules/Platform/resources/views/agencies/show.blade.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/views/agencies/show.blade.php');
        $this->assertStringContainsString('agency-command-center', $contents);
        $this->assertStringContainsString('Command Center', $contents);
        $this->assertStringContainsString('Operations', $contents);
        $this->assertStringContainsString('ag-action-grid', $contents);
        $this->assertStringContainsString('ag-health-chip', $contents);
        $this->assertStringContainsString('ag-metric-box', $contents);
        $this->assertStringContainsString('Capacity', $contents);
        $this->assertStringContainsString('?section=servers', $contents);
        $this->assertStringContainsString('?section=providers', $contents);
        $this->assertStringContainsString('<x-tabs param="section"', $contents);
        $this->assertStringContainsString('class="border shadow-none"', $contents);
    }
}
