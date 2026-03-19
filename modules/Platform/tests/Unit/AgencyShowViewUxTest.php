<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class AgencyShowViewUxTest extends TestCase
{
    public function test_agency_show_page_uses_command_center_operations_and_tabs_layout(): void
    {
        $pagePath = base_path('modules/Platform/resources/js/pages/platform/agencies/show.tsx');
        $pageContents = file_get_contents($pagePath);
        $overviewPath = base_path('modules/Platform/resources/js/pages/platform/agencies/components/agency-show-overview.tsx');
        $overviewContents = file_get_contents($overviewPath);
        $tabsPath = base_path('modules/Platform/resources/js/pages/platform/agencies/components/agency-show-tabs.tsx');
        $tabsContents = file_get_contents($tabsPath);

        $this->assertNotFalse($pageContents, 'Failed to read modules/Platform/resources/js/pages/platform/agencies/show.tsx');
        $this->assertNotFalse($overviewContents, 'Failed to read modules/Platform/resources/js/pages/platform/agencies/components/agency-show-overview.tsx');
        $this->assertNotFalse($tabsContents, 'Failed to read modules/Platform/resources/js/pages/platform/agencies/components/agency-show-tabs.tsx');
        $this->assertStringContainsString('AgencyShowOverview', $pageContents);
        $this->assertStringContainsString('AgencyShowTabs', $pageContents);
        $this->assertStringContainsString('Command Center', $overviewContents);
        $this->assertStringContainsString('Operations', $overviewContents);
        $this->assertStringContainsString('HealthChip', $overviewContents);
        $this->assertStringContainsString('MetricBox', $overviewContents);
        $this->assertStringContainsString('Capacity', $overviewContents);
        $this->assertStringContainsString('<Tabs', $tabsContents);
        $this->assertStringContainsString('<TabsTrigger', $tabsContents);
        $this->assertStringContainsString('Secret Key', $tabsContents);
        $this->assertStringContainsString('Webhook URL', $tabsContents);
        $this->assertStringContainsString('AgencyMetadataTab', $tabsContents);
    }

    public function test_agency_show_controller_exposes_operational_summary_fields_for_react_show_page(): void
    {
        $path = base_path('modules/Platform/app/Http/Controllers/AgencyController.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Controllers/AgencyController.php');
        $this->assertStringContainsString("'has_secret_key' => ! empty(\$agency->secret_key)", $contents);
        $this->assertStringContainsString("'is_whitelabel' => \$agency->isWhitelabel()", $contents);
        $this->assertStringContainsString("'statistics' => [", $contents);
        $this->assertStringContainsString("'agency_website' => \$agency->agencyWebsite", $contents);
        $this->assertStringContainsString("'status_label' => \$server->status_label", $contents);
        $this->assertStringContainsString("'vendor_label' => \$provider->vendor_label", $contents);
        $this->assertStringContainsString("'href' => route('platform.providers.show', \$provider)", $contents);
        $this->assertStringContainsString("'is_primary' => (bool) (\$server->pivot?->is_primary ?? false)", $contents);
        $this->assertStringContainsString("'is_primary' => (bool) (\$provider->pivot?->is_primary ?? false)", $contents);
    }
}
