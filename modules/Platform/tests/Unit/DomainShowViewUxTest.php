<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class DomainShowViewUxTest extends TestCase
{
    public function test_domain_show_view_uses_command_center_layout_and_section_tabs(): void
    {
        $path = base_path('modules/Platform/resources/views/domains/show.blade.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/views/domains/show.blade.php');
        $this->assertStringContainsString('domain-command-center', $contents);
        $this->assertStringContainsString('Command Center', $contents);
        $this->assertStringContainsString('Operations', $contents);
        $this->assertStringContainsString('dm-action-grid', $contents);
        $this->assertStringContainsString('dm-health-chip', $contents);
        $this->assertStringContainsString('dm-metric-box', $contents);
        $this->assertStringContainsString('Renewal Window', $contents);
        $this->assertStringContainsString("route('platform.domains.show', \$domain->id) }}?section=ssl", $contents);
        $this->assertStringContainsString('<x-tabs param="section"', $contents);
        $this->assertStringContainsString('class="border shadow-none"', $contents);
    }
}
