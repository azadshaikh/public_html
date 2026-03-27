<?php

namespace Modules\Platform\Tests\Unit;

use App\Enums\ActivityAction;
use Illuminate\Support\Facades\Artisan;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteProvisioningService;
use Tests\TestCase;

class TestableWebsiteForPrimaryHostname extends Website
{
    public ?Provider $cdnProviderStub = null;

    public ?Provider $dnsProviderStub = null;

    public int $saveCount = 0;

    protected function getCdnProviderAttribute(): ?Provider
    {
        return $this->cdnProviderStub;
    }

    protected function getDnsProviderAttribute(): ?Provider
    {
        return $this->dnsProviderStub;
    }

    public function save(array $options = []): bool
    {
        $this->saveCount++;

        return true;
    }
}

class TestableWebsiteProvisioningPrimaryHostnameService extends WebsiteProvisioningService
{
    public int $loggedActivities = 0;

    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->loggedActivities++;
    }
}

class WebsiteProvisioningPrimaryHostnameTest extends TestCase
{
    public function test_update_primary_hostname_rejects_subdomains(): void
    {
        $service = new TestableWebsiteProvisioningPrimaryHostnameService;
        $website = new TestableWebsiteForPrimaryHostname;
        $website->id = 10;
        $website->domain = 'app.astero.in';

        $result = $service->updatePrimaryHostname($website, true);

        $this->assertSame('error', $result['status']);
        $this->assertSame(422, $result['code']);
        $this->assertSame(0, $website->saveCount);
    }

    public function test_update_primary_hostname_runs_reconciliation_steps_and_returns_info_when_ssl_is_waiting(): void
    {
        $service = new TestableWebsiteProvisioningPrimaryHostnameService;
        $website = new TestableWebsiteForPrimaryHostname;
        $website->id = 20;
        $website->domain = 'astero.in';
        $website->uid = 'asteroin';
        $website->is_www = false;
        $website->cdnProviderStub = new Provider(['vendor' => 'bunny']);
        $website->setRelation('domainRecord', new Domain(['name' => 'astero.in']));
        $website->setRelation('providers', collect());

        Artisan::shouldReceive('call')
            ->once()
            ->with('platform:hestia:create-website', ['website_id' => 20])
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('');

        Artisan::shouldReceive('call')
            ->once()
            ->with('platform:bunny:setup-cdn', ['website_id' => 20])
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('');

        Artisan::shouldReceive('call')
            ->once()
            ->with('platform:bunny:configure-cdn-ssl', ['website_id' => 20])
            ->andReturn(2);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Waiting for Bunny custom hostname SSL to become active.');

        $result = $service->updatePrimaryHostname($website, true);

        $this->assertSame('info', $result['status']);
        $this->assertStringContainsString('www.astero.in', $result['message']);
        $this->assertStringContainsString('CDN SSL is still reconciling', $result['message']);
        $this->assertSame(1, $service->loggedActivities);
        $this->assertGreaterThanOrEqual(1, $website->saveCount);
        $this->assertTrue($website->usesWwwPrimary());
    }
}
