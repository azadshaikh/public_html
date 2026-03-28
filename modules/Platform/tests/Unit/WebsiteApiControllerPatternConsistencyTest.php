<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class WebsiteApiControllerPatternConsistencyTest extends TestCase
{
    public function test_website_api_controller_uses_refactored_concerns(): void
    {
        $controllerPath = base_path('modules/Platform/app/Http/Controllers/Api/V1/WebsiteApiController.php');
        $controllerContents = file_get_contents($controllerPath);

        $this->assertNotFalse($controllerContents, 'Failed to read modules/Platform/app/Http/Controllers/Api/V1/WebsiteApiController.php');
        $this->assertStringContainsString('use InteractsWithWebsiteApiDnsAndCdn;', $controllerContents);
        $this->assertStringContainsString('use InteractsWithWebsiteApiProvisioning;', $controllerContents);
        $this->assertStringNotContainsString('public function dnsRecords(', $controllerContents);
        $this->assertStringNotContainsString('public function getCdnStatus(', $controllerContents);
        $this->assertStringNotContainsString('public function provisioning(', $controllerContents);
    }

    public function test_website_api_concerns_hold_dns_cdn_and_provisioning_behaviour(): void
    {
        $dnsAndCdnConcernPath = base_path('modules/Platform/app/Http/Controllers/Concerns/InteractsWithWebsiteApiDnsAndCdn.php');
        $dnsAndCdnConcernContents = file_get_contents($dnsAndCdnConcernPath);

        $this->assertNotFalse($dnsAndCdnConcernContents, 'Failed to read InteractsWithWebsiteApiDnsAndCdn concern.');
        $this->assertStringContainsString('public function dnsRecords(', $dnsAndCdnConcernContents);
        $this->assertStringContainsString('public function addDnsRecord(', $dnsAndCdnConcernContents);
        $this->assertStringContainsString('public function updateDnsRecord(', $dnsAndCdnConcernContents);
        $this->assertStringContainsString('public function deleteDnsRecord(', $dnsAndCdnConcernContents);
        $this->assertStringContainsString('public function getCdnStatus(', $dnsAndCdnConcernContents);
        $this->assertStringContainsString('public function purgeCdnCache(', $dnsAndCdnConcernContents);

        $provisioningConcernPath = base_path('modules/Platform/app/Http/Controllers/Concerns/InteractsWithWebsiteApiProvisioning.php');
        $provisioningConcernContents = file_get_contents($provisioningConcernPath);

        $this->assertNotFalse($provisioningConcernContents, 'Failed to read InteractsWithWebsiteApiProvisioning concern.');
        $this->assertStringContainsString('public function provisioning(', $provisioningConcernContents);
        $this->assertStringContainsString('public function retryProvision(', $provisioningConcernContents);
        $this->assertStringContainsString('public function confirmDns(', $provisioningConcernContents);
        $this->assertStringContainsString('protected function resolveAgency(', $provisioningConcernContents);
        $this->assertStringContainsString('protected function findWebsiteOrFail(', $provisioningConcernContents);
    }
}
