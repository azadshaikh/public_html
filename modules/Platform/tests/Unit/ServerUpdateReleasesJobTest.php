<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Jobs\ServerUpdateReleases;
use Modules\Platform\Models\Server;
use Tests\TestCase;

class TestableServerUpdateReleasesJob extends ServerUpdateReleases
{
    public int $testAttempts = 1;

    public function attempts(): int
    {
        return $this->testAttempts;
    }
}

class ServerUpdateReleasesJobTest extends TestCase
{
    public function test_it_is_configured_to_fail_fast_without_retries(): void
    {
        $server = new Server;
        $server->id = 1;

        $job = new TestableServerUpdateReleasesJob($server);

        $this->assertSame(1, $job->tries);
        $this->assertSame(1200, $job->timeout);
    }
}
