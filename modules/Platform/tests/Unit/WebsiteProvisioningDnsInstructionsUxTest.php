<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class WebsiteProvisioningDnsInstructionsUxTest extends TestCase
{
    public function test_website_provisioning_views_render_shared_dns_instruction_component(): void
    {
        $sharedComponentPath = base_path('modules/Platform/resources/js/pages/platform/websites/components/website-provisioning-dns-instructions.tsx');
        $tablePath = base_path('modules/Platform/resources/js/pages/platform/websites/components/website-provisioning-steps-table.tsx');
        $showTabsPath = base_path('modules/Platform/resources/js/pages/platform/websites/components/website-show-tabs.tsx');

        $sharedComponentContents = file_get_contents($sharedComponentPath);
        $tableContents = file_get_contents($tablePath);
        $showTabsContents = file_get_contents($showTabsPath);

        $this->assertNotFalse($sharedComponentContents, 'Failed to read shared DNS instructions component.');
        $this->assertNotFalse($tableContents, 'Failed to read website provisioning steps table.');
        $this->assertNotFalse($showTabsContents, 'Failed to read website tabs component.');

        $this->assertStringContainsString('Add these DNS records:', $sharedComponentContents);
        $this->assertStringContainsString('Update the domain nameservers to:', $sharedComponentContents);
        $this->assertStringContainsString('WebsiteProvisioningDnsInstructions', $tableContents);
        $this->assertStringContainsString('WebsiteProvisioningStepsTable', $showTabsContents);
    }
}
