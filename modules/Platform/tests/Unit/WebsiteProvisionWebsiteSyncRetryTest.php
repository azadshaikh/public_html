<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteService;
use Tests\TestCase;

class WebsiteProvisionWebsiteSyncRetryTest extends TestCase
{
    public function test_sync_website_retries_until_runtime_metadata_is_available(): void
    {
        $website = new Website;
        $website->id = 101;

        $job = new class($website) extends WebsiteProvision
        {
            public int $attempts = 0;

            public function runSyncWebsite(Website $website): void
            {
                $this->syncWebsite($website);
            }

            protected function performWebsiteSyncAttempt(WebsiteService $websiteService, Website $website): array
            {
                $this->attempts++;

                if ($this->attempts === 2) {
                    $website->astero_version = '1.2.3';

                    return [
                        'success' => true,
                        'message' => 'Website information synced successfully.',
                    ];
                }

                return [
                    'success' => false,
                    'info' => true,
                    'message' => 'Website synced but no information was updated.',
                ];
            }

            protected function pauseBetweenWebsiteSyncAttempts(): void {}
        };

        $job->runSyncWebsite($website);

        $this->assertSame(2, $job->attempts);
        $this->assertSame('1.2.3', $website->astero_version);
    }

    public function test_sync_website_applies_server_version_fallback_after_retries(): void
    {
        $server = new Server;
        $server->id = 77;
        $server->setMetadata('astero_version', '9.9.9');

        $website = new Website;
        $website->id = 102;
        $website->setRelation('server', $server);

        $job = new class($website) extends WebsiteProvision
        {
            public int $attempts = 0;

            public function runSyncWebsite(Website $website): void
            {
                $this->syncWebsite($website);
            }

            protected function performWebsiteSyncAttempt(WebsiteService $websiteService, Website $website): array
            {
                $this->attempts++;

                return [
                    'success' => false,
                    'info' => true,
                    'message' => 'Website synced but no information was updated.',
                ];
            }

            protected function pauseBetweenWebsiteSyncAttempts(): void {}
        };

        $job->runSyncWebsite($website);

        $this->assertSame(4, $job->attempts);
        $this->assertSame('9.9.9', $website->astero_version);
    }
}
