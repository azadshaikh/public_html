<?php

namespace Modules\ReleaseManager\Database\Seeders;

use Illuminate\Database\Seeder;

class ReleaseManagerDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);
    }
}
