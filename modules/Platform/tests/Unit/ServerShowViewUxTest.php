<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerShowViewUxTest extends TestCase
{
    public function test_server_show_view_uses_command_center_layout_and_section_tabs(): void
    {
        $path = base_path('modules/Platform/resources/views/servers/show.blade.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/views/servers/show.blade.php');
        $this->assertStringContainsString('server-command-center', $contents);
        $this->assertStringContainsString('Command Center', $contents);
        $this->assertStringContainsString('Operations', $contents);
        $this->assertStringContainsString('sv-action-grid', $contents);
        $this->assertStringContainsString('sv-health-chip', $contents);
        $this->assertStringContainsString('sv-metric-box', $contents);
        $this->assertStringContainsString('Capacity', $contents);
        $this->assertStringContainsString('<x-tabs param="section"', $contents);
        $this->assertStringContainsString('class="border shadow-none"', $contents);
        $this->assertStringContainsString("'provisioning'", $contents);
        $this->assertStringContainsString('$isProvisionModeServer', $contents);
        $this->assertStringContainsString('$showProvisioningTab = $isProvisionModeServer;', $contents);
        $this->assertStringContainsString('if ($isProvisionModeServer && $server->canProvision() && $server->hasSshCredentials())', $contents);
        $this->assertStringContainsString('Stop Provisioning', $contents);
        $this->assertStringContainsString("route('platform.servers.stop-provisioning', \$server->id)", $contents);
        $this->assertStringContainsString('stopProvisioning()', $contents);
        $this->assertStringContainsString('getStepOutput(stepKey)', $contents);
        $this->assertStringContainsString('<summary class="text-muted">Output</summary>', $contents);
        $this->assertStringContainsString('stepData.log_tail || stepData.output_tail ||', $contents);
        $this->assertStringNotContainsString('Provisioning: {{ $server->provisioning_status_label }}', $contents);
    }
}
