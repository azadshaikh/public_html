<?php

namespace Modules\Todos\Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'view_todos',
                'display_name' => 'View Todos',
                'group' => 'todo_management',
            ],
            [
                'name' => 'add_todos',
                'display_name' => 'Add Todos',
                'group' => 'todo_management',
            ],
            [
                'name' => 'edit_todos',
                'display_name' => 'Edit Todos',
                'group' => 'todo_management',
            ],
            [
                'name' => 'delete_todos',
                'display_name' => 'Delete Todos',
                'group' => 'todo_management',
            ],
            [
                'name' => 'change_status_todos',
                'display_name' => 'Change Status Todos',
                'group' => 'todo_management',
            ],
            [
                'name' => 'restore_todos',
                'display_name' => 'Restore Todos',
                'group' => 'todo_management',
            ],
        ];

        foreach ($permissions as $p) {
            $data = [
                'display_name' => $p['display_name'],
                'guard_name' => 'web',
                'group' => $p['group'],
                'module_slug' => 'todos',
                'created_by' => 1,
                'updated_by' => 1,
            ];

            $permission = Permission::query()->updateOrCreate(['name' => $p['name']], $data);

            $this->command->info('Seeded permission: '.$permission->name);
        }

        $admin = Role::query()->where('name', 'administrator')->first();
        if (! $admin) {
            $this->command->warn('Administrator role not found. Skipping TODO permission assignment.');

            return;
        }

        $todoPermissionIds = Permission::query()->where('module_slug', 'todos')->pluck('id')->toArray();
        if (empty($todoPermissionIds)) {
            $this->command->warn('No TODO permissions found to assign to administrator.');

            return;
        }

        $admin->permissions()->syncWithoutDetaching($todoPermissionIds);
        $this->command->info('Assigned TODO permissions to administrator role.');
    }
}
