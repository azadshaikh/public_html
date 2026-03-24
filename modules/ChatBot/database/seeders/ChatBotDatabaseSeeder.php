<?php

declare(strict_types=1);

namespace Modules\ChatBot\Database\Seeders;

use Illuminate\Database\Seeder;

class ChatBotDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ChatBotSettingsSeeder::class,
            ChatBotPermissionSeeder::class,
        ]);
    }
}
