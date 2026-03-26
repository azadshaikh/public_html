<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class DomainShowViewUxTest extends TestCase
{
    public function test_domain_show_view_uses_command_center_layout_and_section_tabs(): void
    {
        $path = base_path('modules/Platform/resources/js/pages/platform/domains/show.tsx');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/js/pages/platform/domains/show.tsx');
        $this->assertStringContainsString('AppLayout', $contents);
        $this->assertStringContainsString('Domain overview', $contents);
        $this->assertStringContainsString('SSL certificates', $contents);
        $this->assertStringContainsString('Name servers', $contents);
        $this->assertStringContainsString('Manage DNS', $contents);
        $this->assertStringContainsString("route('platform.dns.index', { status: 'all', domain_id: domain.id })", $contents);
        $this->assertStringContainsString("'platform.domains.ssl-certificates.generate-self-signed'", $contents);
        $this->assertStringContainsString("'platform.domains.ssl-certificates.create'", $contents);
    }
}
