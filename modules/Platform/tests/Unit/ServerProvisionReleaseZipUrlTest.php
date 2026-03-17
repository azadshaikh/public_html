<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Jobs\ServerProvision;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\ServerSSHService;
use Tests\TestCase;

class FakeServerServiceForReleaseZipUrl extends ServerService
{
    public bool $updateLocalReleasesCalled = false;

    /** @var array<string, mixed> */
    public array $updateLocalReleasesResult = [
        'success' => true,
        'message' => 'ok',
        'data' => [
            'version' => '1.0.28',
            'execution_path' => 'hestia-api',
        ],
    ];

    public function updateLocalReleases(Server $server): array
    {
        $this->updateLocalReleasesCalled = true;

        return $this->updateLocalReleasesResult;
    }
}

class TestableServerProvisionReleaseZipUrl extends ServerProvision
{
    public bool $setupReleaseFromZipUrlCalled = false;

    public function updateReleasesPublic(Server $server, ServerSSHService $sshService, ServerService $serverService): array
    {
        return $this->updateReleases($server, $sshService, $serverService);
    }

    protected function setupReleaseFromZipUrl(Server $server, ServerSSHService $sshService, string $releaseZipUrl): array
    {
        $this->setupReleaseFromZipUrlCalled = true;

        return [
            'summary' => 'Release setup completed from release zip URL.',
            'version' => '1.0.28',
            'execution_path' => 'ssh-zip-url',
        ];
    }

    protected function configureReleaseApiKey(Server $server, ServerSSHService $sshService): array
    {
        return [
            'summary' => 'Skipped in test.',
        ];
    }
}

class ServerProvisionReleaseZipUrlTest extends TestCase
{
    public function test_update_releases_uses_zip_url_path_when_present(): void
    {
        $server = new Server;
        $server->id = 1;
        $server->metadata = [
            'release_zip_url' => 'https://asteroreleases.b-cdn.net/application/main/20260215_024209_v1.0.28_release.zip',
        ];

        $job = new TestableServerProvisionReleaseZipUrl($server);
        $service = new FakeServerServiceForReleaseZipUrl;

        $result = $job->updateReleasesPublic($server, new ServerSSHService, $service);

        $this->assertTrue($job->setupReleaseFromZipUrlCalled);
        $this->assertFalse($service->updateLocalReleasesCalled);
        $this->assertSame('ssh-zip-url', $result['execution_path'] ?? null);
    }

    public function test_update_releases_falls_back_to_hestia_api_when_zip_url_missing(): void
    {
        $server = new Server;
        $server->id = 1;
        $server->metadata = [];

        $job = new TestableServerProvisionReleaseZipUrl($server);
        $service = new FakeServerServiceForReleaseZipUrl;

        $result = $job->updateReleasesPublic($server, new ServerSSHService, $service);

        $this->assertFalse($job->setupReleaseFromZipUrlCalled);
        $this->assertTrue($service->updateLocalReleasesCalled);
        $this->assertSame('hestia-api', $result['execution_path'] ?? null);
    }
}
