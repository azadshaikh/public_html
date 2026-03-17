<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Queue\Middleware\WithoutOverlapping;
use Modules\Platform\Jobs\ServerProvision;
use Modules\Platform\Models\Server;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class TestableServerProvisionReliability extends ServerProvision
{
    public int $mockAttempts = 1;

    public function attempts(): int
    {
        return $this->mockAttempts;
    }

    public function shouldReleaseForRetryPublic(Throwable $exception): bool
    {
        return $this->shouldReleaseForRetry($exception);
    }

    public function backoffSecondsPublic(): int
    {
        return $this->backoffSeconds();
    }
}

class ServerProvisionReliabilityTest extends TestCase
{
    public function test_server_provision_uses_single_retry_policy_with_safe_timeout(): void
    {
        $server = new Server;
        $server->id = 1;

        $job = new TestableServerProvisionReliability($server);

        $this->assertSame(2, $job->tries);
        $this->assertSame(7200, $job->timeout);
        $this->assertGreaterThan(now()->getTimestamp(), $job->retryUntil()->getTimestamp());
    }

    public function test_server_provision_registers_without_overlapping_middleware(): void
    {
        $server = new Server;
        $server->id = 1;

        $job = new TestableServerProvisionReliability($server);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame('server-provision:1', $middleware[0]->key);
        $this->assertSame(120, $middleware[0]->releaseAfter);
        $this->assertSame(7800, $middleware[0]->expiresAfter);
    }

    public function test_server_provision_marks_transient_errors_as_retryable(): void
    {
        $server = new Server;
        $server->id = 1;

        $job = new TestableServerProvisionReliability($server);

        $this->assertTrue($job->shouldReleaseForRetryPublic(new RuntimeException('Connection failed while provisioning.')));
        $this->assertTrue($job->shouldReleaseForRetryPublic(new RuntimeException('HESTIA_INSTALL_STILL_RUNNING: installer still active')));
        $this->assertFalse($job->shouldReleaseForRetryPublic(new RuntimeException('Failed to update releases: API connection failed.')));
        $this->assertFalse($job->shouldReleaseForRetryPublic(new RuntimeException('Access key JSON parsing failed')));
    }

    public function test_server_provision_backoff_schedule_uses_current_attempt(): void
    {
        $server = new Server;
        $server->id = 1;

        $job = new TestableServerProvisionReliability($server);

        $job->mockAttempts = 1;
        $this->assertSame(180, $job->backoffSecondsPublic());

        $job->mockAttempts = 2;
        $this->assertSame(180, $job->backoffSecondsPublic());
    }
}
