<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class WebsiteShowViewUxTest extends TestCase
{
    public function test_website_show_view_uses_command_center_layout_and_runtime_controls(): void
    {
        $path = base_path('modules/Platform/resources/views/websites/show.blade.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/views/websites/show.blade.php');
        $this->assertStringContainsString('website-command-center', $contents);
        $this->assertStringContainsString('Command Center', $contents);
        $this->assertStringContainsString('Operations', $contents);
        $this->assertStringContainsString('Queue Workers', $contents);
        $this->assertStringContainsString('Recache', $contents);
        $this->assertStringContainsString('Scale workers', $contents);
        $this->assertStringContainsString('ws-queue-meter', $contents);
        $this->assertStringContainsString('ws-action-grid', $contents);
        $this->assertStringContainsString('ws-ops-scale-grid', $contents);
        $this->assertStringContainsString("route('platform.websites.recache-application', \$website->id)", $contents);
        $this->assertStringContainsString('<x-tabs param="section"', $contents);
        $this->assertStringContainsString('<x-slot:provision>', $contents);
        $this->assertStringContainsString("route('platform.servers.show', \$website->server->id)", $contents);
        $this->assertStringContainsString("route('platform.agencies.show', \$website->agency->id)", $contents);
        $this->assertStringContainsString("\$website->server->name ?? \$website->server->fqdn ?? '--'", $contents);
        $this->assertStringNotContainsString('url-tab-manager-loader', $contents);
        $this->assertStringNotContainsString('Identity Snapshot', $contents);
        $this->assertStringNotContainsString('Hosting Snapshot', $contents);
        $this->assertStringNotContainsString('Runtime & Services', $contents);
        $this->assertStringNotContainsString('<span class="text-muted">Laravel</span>', $contents);
        $this->assertStringNotContainsString('<span class="text-muted">PHP</span>', $contents);
    }
}
