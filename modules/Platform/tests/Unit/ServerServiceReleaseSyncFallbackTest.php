<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerService;
use Tests\TestCase;

class FakeServerServiceForReleaseSyncFallback extends ServerService
{
    /** @var array<int, array<string, mixed>> */
    public array $hestiaResponses = [];

    /** @var array<int, array<string, mixed>> */
    public array $hestiaCalls = [];

    protected function executeHestiaCommand(string $command, Server $server, array $args = [], int $timeout = 60): array
    {
        $this->hestiaCalls[] = [
            'command' => $command,
            'args' => $args,
            'timeout' => $timeout,
        ];

        return array_shift($this->hestiaResponses) ?? [
            'success' => false,
            'message' => 'Hestia API test default failure',
            'data' => [],
        ];
    }
}

class ServerServiceReleaseSyncFallbackTest extends TestCase
{
    public function test_update_local_releases_fails_fast_when_hestia_api_fails(): void
    {
        $server = new Server;
        $server->ip = '192.168.0.100';
        $server->access_key_id = 'access-key';
        $server->access_key_secret = 'secret-key';

        $service = new FakeServerServiceForReleaseSyncFallback;
        $service->hestiaResponses = [
            ['success' => false, 'message' => 'API connection failed: timeout', 'data' => []],
        ];

        $result = $service->updateLocalReleases($server);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to update releases:', $result['message'] ?? '');
        $this->assertCount(1, $service->hestiaCalls);
    }

    public function test_update_local_releases_succeeds_without_fallback_when_hestia_api_succeeds(): void
    {
        $server = new Server;
        $server->ip = '192.168.0.100';
        $server->access_key_id = 'access-key';
        $server->access_key_secret = 'secret-key';

        $service = new FakeServerServiceForReleaseSyncFallback;
        $service->hestiaResponses = [
            ['success' => true, 'message' => 'ok', 'data' => ['version' => '1.2.3']],
        ];

        $result = $service->updateLocalReleases($server);

        $this->assertTrue($result['success']);
        $this->assertSame('1.2.3', $result['data']['version'] ?? null);
        $this->assertSame('hestia-api', $result['data']['execution_path'] ?? null);
        $this->assertCount(1, $service->hestiaCalls);
    }

    public function test_update_local_releases_treats_no_update_found_as_non_fatal(): void
    {
        $server = new Server;
        $server->ip = '192.168.0.100';
        $server->access_key_id = 'access-key';
        $server->access_key_secret = 'secret-key';

        $service = new FakeServerServiceForReleaseSyncFallback;
        $service->hestiaResponses = [
            ['success' => false, 'message' => 'Failed to parse API response: Error: API error: No update found', 'data' => []],
        ];

        $result = $service->updateLocalReleases($server);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['no_update'] ?? false);
        $this->assertSame('hestia-api', $result['data']['execution_path'] ?? null);
        $this->assertCount(1, $service->hestiaCalls);
    }
}
