<?php

declare(strict_types=1);

namespace Modules\ChatBot\Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChatBotPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'use_chatbot',
                'display_name' => 'Use ChatBot',
                'group' => 'chatbot',
            ],
            [
                'name' => 'manage_chatbot_settings',
                'display_name' => 'Manage ChatBot Settings',
                'group' => 'chatbot',
            ],
        ];

        $created = collect();

        foreach ($permissions as $data) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $data['name']],
                [
                    'display_name' => $data['display_name'],
                    'group' => $data['group'],
                ]
            );

            $created->push($permission);
        }

        $administratorRole = Role::query()->where('name', 'administrator')->first();

        if ($administratorRole) {
            $administratorRole->permissions()->syncWithoutDetaching($created->pluck('id')->toArray());
            $this->command->info(sprintf('Assigned %s ChatBot permissions to administrator role.', $created->count()));
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('permissions', 'id'), COALESCE((SELECT MAX(id) FROM permissions), 1))");
        }
    }
}
