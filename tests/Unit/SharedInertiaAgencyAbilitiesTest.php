<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SharedInertiaAgencyAbilitiesTest extends TestCase
{
    public function test_platform_agency_routes_include_crud_ability_keys_in_shared_inertia_payload(): void
    {
        $path = dirname(__DIR__, 2).'/app/Http/Middleware/HandleInertiaRequests.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read app/Http/Middleware/HandleInertiaRequests.php');
        $this->assertStringContainsString(
            "\$request->routeIs('platform.agencies.*') => ['addAgencies', 'editAgencies', 'deleteAgencies', 'restoreAgencies']",
            $contents
        );
    }
}
