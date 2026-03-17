<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            ProviderSeeder::class,
            TldSeeder::class,
            ServerSeeder::class,
            AgencySeeder::class,
        ]);
    }
}
