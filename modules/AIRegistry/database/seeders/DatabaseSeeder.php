<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AIRegistryDatabaseSeeder::class);
    }
}
