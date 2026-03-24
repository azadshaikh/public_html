<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Database\Seeders;

use Illuminate\Database\Seeder;

class AIRegistryDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            AIRegistrySeeder::class,
        ]);
    }
}
