<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class AgencyShowManagementUxTest extends TestCase
{
    public function test_agency_show_management_components_are_split_and_keep_reasonable_file_size(): void
    {
        $files = [
            'modules/Platform/resources/js/pages/platform/agencies/show.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/components/show-shared.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/components/agency-show-overview.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/components/agency-show-tabs.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/components/agency-servers-tab.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/components/agency-providers-tab.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/components/agency-association-management-dialog.tsx',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents(base_path($file));

            $this->assertNotFalse($contents, "Failed to read {$file}");
            $this->assertLessThanOrEqual(900, count(file(base_path($file))), "{$file} should stay under the agreed single-file limit.");
        }
    }

    public function test_agency_server_and_provider_management_ui_is_present_in_split_components(): void
    {
        $serversTabPath = base_path('modules/Platform/resources/js/pages/platform/agencies/components/agency-servers-tab.tsx');
        $serversTabContents = file_get_contents($serversTabPath);
        $this->assertNotFalse($serversTabContents, 'Failed to read modules/Platform/resources/js/pages/platform/agencies/components/agency-servers-tab.tsx');
        $this->assertStringContainsString('Manage Agency Servers', $serversTabContents);
        $this->assertStringContainsString('platform.agencies.servers.attach', $serversTabContents);
        $this->assertStringContainsString('platform.agencies.servers.set-primary', $serversTabContents);
        $this->assertStringContainsString('platform.agencies.servers.detach', $serversTabContents);

        $providersTabPath = base_path('modules/Platform/resources/js/pages/platform/agencies/components/agency-providers-tab.tsx');
        $providersTabContents = file_get_contents($providersTabPath);
        $this->assertNotFalse($providersTabContents, 'Failed to read modules/Platform/resources/js/pages/platform/agencies/components/agency-providers-tab.tsx');
        $this->assertStringContainsString('Manage DNS Providers', $providersTabContents);
        $this->assertStringContainsString('Manage CDN Providers', $providersTabContents);
        $this->assertStringContainsString('platform.agencies.dns-providers.attach', $providersTabContents);
        $this->assertStringContainsString('platform.agencies.cdn-providers.attach', $providersTabContents);
        $this->assertStringContainsString('platform.agencies.providers.detach', $providersTabContents);
    }

    public function test_agency_attach_requests_allow_empty_sync_and_validate_primary_membership(): void
    {
        $serverRequestPath = base_path('modules/Platform/app/Http/Requests/AgencyAttachServersRequest.php');
        $serverRequestContents = file_get_contents($serverRequestPath);
        $this->assertNotFalse($serverRequestContents, 'Failed to read modules/Platform/app/Http/Requests/AgencyAttachServersRequest.php');
        $this->assertStringContainsString("'server_ids' => ['present', 'array']", $serverRequestContents);
        $this->assertStringContainsString('The selected primary server must be included in server_ids.', $serverRequestContents);

        $dnsRequestPath = base_path('modules/Platform/app/Http/Requests/AgencyAttachDnsProvidersRequest.php');
        $dnsRequestContents = file_get_contents($dnsRequestPath);
        $this->assertNotFalse($dnsRequestContents, 'Failed to read modules/Platform/app/Http/Requests/AgencyAttachDnsProvidersRequest.php');
        $this->assertStringContainsString("'provider_ids' => ['present', 'array']", $dnsRequestContents);

        $cdnRequestPath = base_path('modules/Platform/app/Http/Requests/AgencyAttachCdnProvidersRequest.php');
        $cdnRequestContents = file_get_contents($cdnRequestPath);
        $this->assertNotFalse($cdnRequestContents, 'Failed to read modules/Platform/app/Http/Requests/AgencyAttachCdnProvidersRequest.php');
        $this->assertStringContainsString("'provider_ids' => ['present', 'array']", $cdnRequestContents);
    }
}
