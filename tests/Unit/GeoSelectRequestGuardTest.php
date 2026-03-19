<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class GeoSelectRequestGuardTest extends TestCase
{
    public function test_state_select_uses_fetch_instead_of_use_http_effect_dependency(): void
    {
        $path = dirname(__DIR__, 2).'/resources/js/components/geo/state-select.tsx';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read resources/js/components/geo/state-select.tsx');
        $this->assertStringNotContainsString("useHttp } from '@inertiajs/react';", $contents);
        $this->assertStringContainsString('const controller = new AbortController();', $contents);
        $this->assertStringContainsString('void fetch(url, {', $contents);
        $this->assertStringContainsString('signal: controller.signal,', $contents);
        $this->assertStringContainsString('controller.abort();', $contents);
        $this->assertStringContainsString('const [loadedCountryCode, setLoadedCountryCode] = useState(\'\');', $contents);
        $this->assertStringContainsString('const loading = Boolean(countryCode) && loadedCountryCode !== countryCode;', $contents);
        $this->assertStringContainsString('}, [countryCode]);', $contents);
    }

    public function test_city_select_uses_fetch_instead_of_use_http_effect_dependency(): void
    {
        $path = dirname(__DIR__, 2).'/resources/js/components/geo/city-select.tsx';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read resources/js/components/geo/city-select.tsx');
        $this->assertStringNotContainsString("useHttp } from '@inertiajs/react';", $contents);
        $this->assertStringContainsString('const controller = new AbortController();', $contents);
        $this->assertStringContainsString('void fetch(url, {', $contents);
        $this->assertStringContainsString('signal: controller.signal,', $contents);
        $this->assertStringContainsString('controller.abort();', $contents);
        $this->assertStringContainsString('const loading = Boolean(requestKey) && loadedKey !== requestKey;', $contents);
        $this->assertStringContainsString('}, [countryCode, requestKey, stateCode]);', $contents);
    }
}
