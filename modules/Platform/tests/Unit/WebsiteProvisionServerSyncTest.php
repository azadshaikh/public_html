<?php

namespace Modules\Platform\Tests\Unit;

use App\Enums\ActivityAction;
use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerService;
use ReflectionClass;
use Tests\TestCase;

class WebsiteProvisionServerSyncTest extends TestCase
{
    public function test_on_success_runs_server_sync_after_website_sync(): void
    {
        $website = new Website;
        $website->id = 1;

        $job = new class($website) extends WebsiteProvision
        {
            /** @var array<int, string> */
            public array $syncSequence = [];

            protected function syncWebsite(Website $website): void
            {
                $this->syncSequence[] = 'website';
            }

            protected function syncServer(Website $website): void
            {
                $this->syncSequence[] = 'server';
            }

            public function logActivity($model, ActivityAction $action, string $message, array $extraProperties = [], bool $queue = false): void {}
        };

        $job->onSuccess($website);

        $this->assertSame(['website', 'server'], $job->syncSequence);
    }

    public function test_sync_server_calls_server_service_when_server_relation_exists(): void
    {
        $server = new Server;
        $server->id = 7;

        $website = new Website;
        $website->id = 10;
        $website->setRelation('server', $server);

        /** @var ServerService&MockInterface $serverService */
        $serverService = Mockery::mock(ServerService::class);
        $serverService->expects('syncServerInfo')
            ->withArgs(fn (Server $syncedServer): bool => $syncedServer->id === $server->id)
            ->andReturn(['success' => true]);

        $this->app->instance(ServerService::class, $serverService);

        $job = new WebsiteProvision($website);

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('syncServer');

        $method->invoke($job, $website);

        $this->assertSame(10, $website->id);
    }

    public function test_sync_server_skips_when_no_server_relation_exists(): void
    {
        $website = new Website;
        $website->id = 11;

        /** @var ServerService&MockInterface $serverService */
        $serverService = Mockery::mock(ServerService::class);
        $serverService->shouldNotReceive('syncServerInfo');

        $this->app->instance(ServerService::class, $serverService);

        $job = new WebsiteProvision($website);

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('syncServer');

        $method->invoke($job, $website);

        $this->assertSame(11, $website->id);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
