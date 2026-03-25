<?php

declare(strict_types=1);

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Database\Seeders\AgencySeeder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AgencySeederServerMappingTest extends TestCase
{
    public function test_agency_seeder_uses_the_expected_primary_server_ips(): void
    {
        $reflection = new ReflectionClass(AgencySeeder::class);

        $this->assertSame(
            '192.168.0.100',
            $reflection->getReflectionConstant('DEMO_AGENCY_PRIMARY_SERVER_IP')?->getValue(),
        );
        $this->assertSame(
            '192.168.0.150',
            $reflection->getReflectionConstant('LEGACY_AGENCY_PRIMARY_SERVER_IP')?->getValue(),
        );
    }
}
