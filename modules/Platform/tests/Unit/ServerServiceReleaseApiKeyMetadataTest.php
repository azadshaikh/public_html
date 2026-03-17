<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Services\ServerService;
use ReflectionClass;
use Tests\TestCase;

class ServerServiceReleaseApiKeyMetadataTest extends TestCase
{
    public function test_extract_metadata_fields_excludes_release_api_key_when_present(): void
    {
        $service = new ServerService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractMetadataFields');

        /** @var array<string, mixed> $metadata */
        $metadata = $method->invoke($service, [
            'release_api_key' => 'release-key-123',
            'server_cpu' => '8 vCPU',
        ]);

        $this->assertArrayNotHasKey('release_api_key', $metadata);
        $this->assertSame('8 vCPU', $metadata['server_cpu'] ?? null);
    }

    public function test_extract_metadata_fields_ignores_release_api_key_when_blank(): void
    {
        $service = new ServerService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractMetadataFields');

        /** @var array<string, mixed> $metadata */
        $metadata = $method->invoke($service, [
            'release_api_key' => '   ',
            'server_cpu' => '8 vCPU',
        ]);

        $this->assertArrayNotHasKey('release_api_key', $metadata);
        $this->assertSame('8 vCPU', $metadata['server_cpu'] ?? null);
    }
}
