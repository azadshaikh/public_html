<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerShowViewUxTest extends TestCase
{
    public function test_server_show_view_uses_command_center_layout_and_section_tabs(): void
    {
        $overviewPath = base_path('modules/Platform/resources/js/pages/platform/servers/components/server-show-overview.tsx');
        $tabsPath = base_path('modules/Platform/resources/js/pages/platform/servers/components/server-show-tabs.tsx');
        $stepsPath = base_path('modules/Platform/resources/js/pages/platform/servers/components/server-provisioning-steps-table.tsx');

        $overviewContents = file_get_contents($overviewPath);
        $tabsContents = file_get_contents($tabsPath);
        $stepsContents = file_get_contents($stepsPath);

        $this->assertNotFalse($overviewContents, 'Failed to read modules/Platform/resources/js/pages/platform/servers/components/server-show-overview.tsx');
        $this->assertNotFalse($tabsContents, 'Failed to read modules/Platform/resources/js/pages/platform/servers/components/server-show-tabs.tsx');
        $this->assertNotFalse($stepsContents, 'Failed to read modules/Platform/resources/js/pages/platform/servers/components/server-provisioning-steps-table.tsx');
        $this->assertStringContainsString('Command Center', $overviewContents);
        $this->assertStringContainsString('Operations', $overviewContents);
        $this->assertStringContainsString('Stop Provisioning', $overviewContents);
        $this->assertStringContainsString("route('platform.servers.stop-provisioning', server.id)", $overviewContents);
        $this->assertStringContainsString('TabsTrigger value="provision"', $tabsContents);
        $this->assertStringContainsString('TabsTrigger value="secrets"', $tabsContents);
        $this->assertStringContainsString('TabsTrigger value="websites"', $tabsContents);
        $this->assertStringContainsString('Provisioning Steps', $stepsContents);
        $this->assertStringContainsString("method: 'POST'", $stepsContents);
        $this->assertStringContainsString("const PROVISIONING_POLL_INTERVAL_LABEL = 'every 10 seconds';", $stepsContents);
        $this->assertStringContainsString('Auto-updating ${PROVISIONING_POLL_INTERVAL_LABEL} while provisioning is running.', $stepsContents);
    }
}
