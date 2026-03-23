<?php

namespace Modules\Customers\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            CustomerSeeder::class,
            CustomerContactSeeder::class,
        ]);
    }
}
