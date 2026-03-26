<?php

namespace Tests\Feature\Platform\Feature;

use Tests\TestCase;

class PlatformWebsiteDnsModeFormTest extends TestCase
{
    public function test_platform_website_dns_modes_are_configured(): void
    {
        $dnsModes = config('platform.website.dns_modes');

        $this->assertIsArray($dnsModes);
        $this->assertArrayHasKey('subdomain', $dnsModes);
        $this->assertArrayHasKey('managed', $dnsModes);
        $this->assertArrayHasKey('external', $dnsModes);
    }
}
