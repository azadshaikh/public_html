<?php

declare(strict_types=1);

namespace Modules\ChatBot\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChatBotDatabaseSeeder::class);
    }
}
