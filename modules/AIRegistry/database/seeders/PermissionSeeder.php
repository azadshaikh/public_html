<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // AI Providers
            [
                'name' => 'view_ai_providers',
                'display_name' => 'View AI Providers',
                'group' => 'ai_providers',
            ],
            [
                'name' => 'add_ai_providers',
                'display_name' => 'Add AI Providers',
                'group' => 'ai_providers',
            ],
            [
                'name' => 'edit_ai_providers',
                'display_name' => 'Edit AI Providers',
                'group' => 'ai_providers',
            ],
            [
                'name' => 'delete_ai_providers',
                'display_name' => 'Delete AI Providers',
                'group' => 'ai_providers',
            ],
            [
                'name' => 'restore_ai_providers',
                'display_name' => 'Restore AI Providers',
                'group' => 'ai_providers',
            ],

            // AI Models
            [
                'name' => 'view_ai_models',
                'display_name' => 'View AI Models',
                'group' => 'ai_models',
            ],
            [
                'name' => 'add_ai_models',
                'display_name' => 'Add AI Models',
                'group' => 'ai_models',
            ],
            [
                'name' => 'edit_ai_models',
                'display_name' => 'Edit AI Models',
                'group' => 'ai_models',
            ],
            [
                'name' => 'delete_ai_models',
                'display_name' => 'Delete AI Models',
                'group' => 'ai_models',
            ],
            [
                'name' => 'restore_ai_models',
                'display_name' => 'Restore AI Models',
                'group' => 'ai_models',
            ],
        ];

        foreach ($permissions as $p) {
            $data = [
                'display_name' => $p['display_name'],
                'guard_name' => 'web',
                'group' => $p['group'],
                'module_slug' => 'airegistry',
                'created_by' => 1,
                'updated_by' => 1,
            ];

            $permission = Permission::query()->updateOrCreate(['name' => $p['name']], $data);

            $this->command->info('Seeded permission: '.$permission->name);
        }

        // Assign all AIRegistry permissions to administrator role
        $admin = Role::query()->where('name', 'administrator')->first();
        if (! $admin) {
            $this->command->warn('Administrator role not found. Skipping AIRegistry permission assignment.');

            return;
        }

        $permissionIds = Permission::query()->where('module_slug', 'airegistry')->pluck('id')->toArray();
        if (empty($permissionIds)) {
            $this->command->warn('No AIRegistry permissions found to assign to administrator.');

            return;
        }

        $admin->permissions()->syncWithoutDetaching($permissionIds);
        $this->command->info('Assigned AIRegistry permissions to administrator role.');
    }
}
