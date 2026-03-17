<?php

namespace Modules\Platform\Tests\Unit;

use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\WebsiteAccountService;
use Modules\Platform\Services\WebsiteService;
use Tests\TestCase;

class WebsiteServiceSyncNameUpdateTest extends TestCase
{
    public function test_sync_website_info_updates_name_from_app_name_payload(): void
    {
        /** @var ServerService&MockInterface $serverService */
        $serverService = Mockery::mock(ServerService::class);
        /** @var WebsiteAccountService&MockInterface $websiteAccountService */
        $websiteAccountService = Mockery::mock(WebsiteAccountService::class);

        $service = new class($serverService, $websiteAccountService) extends WebsiteService
        {
            protected function fetchWebsiteInfoPayload(Website $website): array
            {
                return [
                    'app_name' => 'Website 3',
                    'astero_version' => '1.0.25',
                    'queue_worker_status' => 'running',
                    'queue_worker_running_count' => 1,
                    'queue_worker_total_count' => 1,
                ];
            }
        };

        $website = new class extends Website
        {
            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $website->name = 'web3';
        $website->domain = 'web3.example.test';
        $website->uid = 'WS00003';
        $website->setRelation('server', new Server);

        $result = $service->syncWebsiteInfo($website);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('Website 3', $website->name);
        $this->assertSame('Website 3', $result['data']['name'] ?? null);
        $this->assertSame('1.0.25', $website->astero_version);
        $this->assertNotNull($website->getMetadata('last_synced_at'));
    }

    public function test_sync_website_info_updates_admin_slug_from_payload(): void
    {
        /** @var ServerService&MockInterface $serverService */
        $serverService = Mockery::mock(ServerService::class);
        /** @var WebsiteAccountService&MockInterface $websiteAccountService */
        $websiteAccountService = Mockery::mock(WebsiteAccountService::class);

        $service = new class($serverService, $websiteAccountService) extends WebsiteService
        {
            protected function fetchWebsiteInfoPayload(Website $website): array
            {
                return [
                    'admin_slug' => '/admin-panel/',
                    'astero_version' => '1.0.25',
                ];
            }
        };

        $website = new class extends Website
        {
            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $website->name = 'web3';
        $website->admin_slug = 'old-admin';
        $website->domain = 'web3.example.test';
        $website->uid = 'WS00003';
        $website->setRelation('server', new Server);

        $result = $service->syncWebsiteInfo($website);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('admin-panel', $website->admin_slug);
        $this->assertSame('admin-panel', $result['data']['admin_slug'] ?? null);
        $this->assertNotNull($website->getMetadata('last_synced_at'));
    }

    public function test_sync_website_info_updates_admin_slug_from_uppercase_payload_key(): void
    {
        /** @var ServerService&MockInterface $serverService */
        $serverService = Mockery::mock(ServerService::class);
        /** @var WebsiteAccountService&MockInterface $websiteAccountService */
        $websiteAccountService = Mockery::mock(WebsiteAccountService::class);

        $service = new class($serverService, $websiteAccountService) extends WebsiteService
        {
            protected function fetchWebsiteInfoPayload(Website $website): array
            {
                return [
                    'ADMIN_SLUG' => '/admin-v2/',
                    'astero_version' => '1.0.25',
                ];
            }
        };

        $website = new class extends Website
        {
            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $website->name = 'web3';
        $website->admin_slug = 'legacy-admin';
        $website->domain = 'web3.example.test';
        $website->uid = 'WS00003';
        $website->setRelation('server', new Server);

        $result = $service->syncWebsiteInfo($website);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('admin-v2', $website->admin_slug);
        $this->assertSame('admin-v2', $result['data']['admin_slug'] ?? null);
        $this->assertNotNull($website->getMetadata('last_synced_at'));
    }
}
