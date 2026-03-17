<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Services\WebsiteService;
use ReflectionClass;
use Tests\TestCase;

class WebsiteServiceSyncPayloadNormalizationTest extends TestCase
{
    public function test_parse_raw_payload_extracts_runtime_metadata_fields(): void
    {
        $service = resolve(WebsiteService::class);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseRawWebsiteInfoPayload');

        /** @var array<string, mixed> $parsed */
        $parsed = $method->invoke($service, implode("\n", [
            'Domain: demo.example.com',
            'App Name: Demo Site',
            'Astero Version: 1.0.25',
            'Laravel Version: 12.8.0',
            'PHP Version: 8.3.17',
            'App Environment: production',
            'App Debug: false',
            'Queue Worker Status: running',
            'Queue Workers Running: 2/4',
            'Cron Status: active',
        ]));

        $this->assertSame('Demo Site', $parsed['app_name'] ?? null);
        $this->assertSame('1.0.25', $parsed['astero_version'] ?? null);
        $this->assertSame('12.8.0', $parsed['laravel_version'] ?? null);
        $this->assertSame('8.3.17', $parsed['php_version'] ?? null);
        $this->assertSame('production', $parsed['app_env'] ?? null);
        $this->assertSame('false', $parsed['app_debug'] ?? null);
        $this->assertSame('running', $parsed['queue_worker_status'] ?? null);
        $this->assertSame(2, $parsed['queue_worker_running_count'] ?? null);
        $this->assertSame(4, $parsed['queue_worker_total_count'] ?? null);
        $this->assertSame('active', $parsed['cron_status'] ?? null);
    }

    public function test_parse_raw_payload_accepts_json_string(): void
    {
        $service = resolve(WebsiteService::class);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseRawWebsiteInfoPayload');

        /** @var array<string, mixed> $parsed */
        $parsed = $method->invoke($service, '{"astero_version":"1.0.25","php_version":"8.3.17"}');

        $this->assertSame('1.0.25', $parsed['astero_version'] ?? null);
        $this->assertSame('8.3.17', $parsed['php_version'] ?? null);
    }

    public function test_parse_raw_payload_extracts_fields_from_malformed_json_like_string(): void
    {
        $service = resolve(WebsiteService::class);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseRawWebsiteInfoPayload');

        $raw = '{"domain":"web3.192.168.0.123.traefik.me","app_name":"Website 3","deploy_type":"release-based","current_release":"v1.0.25","astero_version":"1.0.25","laravel_version":"12.48.1","php_version":"8.3.30","app_env":"production","app_debug":"false","queue_worker":{"status":"unknown 0","running_count":0 1,"total_count":0},"cron":{"status":"active"},"disk_usage_bytes":8160851}';

        /** @var array<string, mixed> $parsed */
        $parsed = $method->invoke($service, $raw);

        $this->assertSame('Website 3', $parsed['app_name'] ?? null);
        $this->assertSame('1.0.25', $parsed['astero_version'] ?? null);
        $this->assertSame('v1.0.25', $parsed['current_release'] ?? null);
        $this->assertSame('12.48.1', $parsed['laravel_version'] ?? null);
        $this->assertSame('8.3.30', $parsed['php_version'] ?? null);
        $this->assertSame('production', $parsed['app_env'] ?? null);
        $this->assertSame('false', $parsed['app_debug'] ?? null);
        $this->assertSame('unknown 0', $parsed['queue_worker_status'] ?? null);
        $this->assertSame(0, $parsed['queue_worker_running_count'] ?? null);
        $this->assertSame(0, $parsed['queue_worker_total_count'] ?? null);
        $this->assertSame('active', $parsed['cron_status'] ?? null);
        $this->assertSame(8160851, $parsed['disk_usage_bytes'] ?? null);
    }

    public function test_queue_worker_status_normalization_handles_legacy_suffix_and_partial_running(): void
    {
        $service = resolve(WebsiteService::class);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeQueueWorkerStatus');

        $unknownWithSuffix = $method->invoke($service, 'unknown 0', 0, 1);
        $partiallyRunning = $method->invoke($service, 'unknown', 1, 2);
        $exited = $method->invoke($service, 'exited', 0, 1);

        $this->assertSame('unknown', $unknownWithSuffix);
        $this->assertSame('degraded', $partiallyRunning);
        $this->assertSame('not_running', $exited);
    }
}
