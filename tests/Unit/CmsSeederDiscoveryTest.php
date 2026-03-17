<?php

namespace Tests\Unit;

use Database\Seeders\DatabaseSeeder as RootDatabaseSeeder;
use Modules\CMS\Database\Seeders\DatabaseSeeder as CmsDatabaseSeeder;
use Tests\TestCase;

class CmsSeederDiscoveryTest extends TestCase
{
    /**
     * CMS module seeders should be autoloadable through the module runtime autoloader.
     */
    public function test_cms_module_database_seeder_is_autoloadable(): void
    {
        $this->assertTrue(class_exists(CmsDatabaseSeeder::class));
    }

    public function test_root_database_seeder_discovers_cms_module_database_seeder(): void
    {
        $seeders = (new RootDatabaseSeeder)->getModuleSeeders();

        $this->assertContains(CmsDatabaseSeeder::class, $seeders);
    }
}
