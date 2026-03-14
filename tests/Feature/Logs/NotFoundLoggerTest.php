<?php

namespace Tests\Feature\Logs;

use App\Models\NotFoundLog;
use App\Services\NotFoundLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class NotFoundLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_web_routes_are_recorded_in_not_found_logs_when_debug_mode_is_enabled(): void
    {
        config()->set('app.debug', true);

        $this->get('/missing-loggable-page?source=test-suite')
            ->assertNotFound();

        $this->assertDatabaseHas('not_found_logs', [
            'url' => '/missing-loggable-page',
            'method' => 'GET',
        ]);

        $log = NotFoundLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('/missing-loggable-page?source=test-suite', parse_url((string) $log->full_url, PHP_URL_PATH).'?'.parse_url((string) $log->full_url, PHP_URL_QUERY));
        $this->assertSame('source=test-suite', data_get($log->metadata, 'query_string'));
    }

    public function test_json_404_responses_are_not_recorded_in_not_found_logs(): void
    {
        config()->set('app.debug', false);

        $this->mock(NotFoundLogger::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('log');
        });

        $this->getJson('/missing-json-page')
            ->assertNotFound();
    }
}
