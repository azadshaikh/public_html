<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class DomainShowViewUxTest extends TestCase
{
    public function test_domain_show_view_uses_command_center_layout_and_section_tabs(): void
    {
        $pagePath = base_path('modules/Platform/resources/js/pages/platform/domains/show.tsx');
        $overviewPath = base_path('modules/Platform/resources/js/pages/platform/domains/components/domain-show-overview.tsx');
        $tabsPath = base_path('modules/Platform/resources/js/pages/platform/domains/components/domain-show-tabs.tsx');
        $pageContents = file_get_contents($pagePath);
        $overviewContents = file_get_contents($overviewPath);
        $tabsContents = file_get_contents($tabsPath);

        $this->assertNotFalse($pageContents, 'Failed to read modules/Platform/resources/js/pages/platform/domains/show.tsx');
        $this->assertNotFalse($overviewContents, 'Failed to read modules/Platform/resources/js/pages/platform/domains/components/domain-show-overview.tsx');
        $this->assertNotFalse($tabsContents, 'Failed to read modules/Platform/resources/js/pages/platform/domains/components/domain-show-tabs.tsx');
        $this->assertStringContainsString('AppLayout', $pageContents);
        $this->assertStringContainsString('DomainShowOverview', $pageContents);
        $this->assertStringContainsString('DomainShowTabs', $pageContents);
        $this->assertStringContainsString('Command Center', $overviewContents);
        $this->assertStringContainsString('Operations', $overviewContents);
        $this->assertStringContainsString('Manage DNS', $overviewContents);
        $this->assertStringContainsString("route('platform.dns.index', { status: 'all', domain_id: domain.id })", $overviewContents);
        $this->assertStringContainsString("'platform.domains.ssl-certificates.generate-self-signed'", $pageContents);
        $this->assertStringContainsString("'platform.domains.ssl-certificates.create'", $pageContents);
        $this->assertStringContainsString('SSL', $tabsContents);
        $this->assertStringContainsString('DNS', $tabsContents);
        $this->assertStringContainsString('Websites', $tabsContents);
        $this->assertStringContainsString('Activity', $tabsContents);
    }
}
