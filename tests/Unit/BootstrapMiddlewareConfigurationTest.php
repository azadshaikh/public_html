<?php

namespace Tests\Unit;

use Tests\TestCase;

class BootstrapMiddlewareConfigurationTest extends TestCase
{
    public function test_bootstrap_registers_proxy_trust_and_global_cdn_cache_headers_middleware(): void
    {
        $path = base_path('bootstrap/app.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read bootstrap/app.php');
        $this->assertStringContainsString('trustProxies(', $contents);
        $this->assertStringContainsString("at: '*'", $contents);
        $this->assertStringContainsString('Request::HEADER_X_FORWARDED_PROTO', $contents);
        $this->assertStringContainsString('Request::HEADER_X_FORWARDED_AWS_ELB', $contents);
        $this->assertStringContainsString('CdnCacheHeadersMiddleware::class', $contents);
    }
}
