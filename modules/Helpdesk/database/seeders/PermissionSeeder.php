<?php

namespace Modules\Helpdesk\Database\Seeders;

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
            // Helpdesk Departments
            ['name' => 'view_helpdesk_departments', 'display_name' => 'View Helpdesk Departments', 'group' => 'helpdesk_departments'],
            ['name' => 'add_helpdesk_departments', 'display_name' => 'Add Helpdesk Departments', 'group' => 'helpdesk_departments'],
            ['name' => 'edit_helpdesk_departments', 'display_name' => 'Edit Helpdesk Departments', 'group' => 'helpdesk_departments'],
            ['name' => 'delete_helpdesk_departments', 'display_name' => 'Delete Helpdesk Departments', 'group' => 'helpdesk_departments'],
            ['name' => 'restore_helpdesk_departments', 'display_name' => 'Restore Helpdesk Departments', 'group' => 'helpdesk_departments'],

            // Helpdesk Settings
            ['name' => 'manage_helpdesk_settings', 'display_name' => 'Manage Helpdesk Settings', 'group' => 'helpdesk_settings'],

            // Helpdesk Tickets
            ['name' => 'view_helpdesk_tickets', 'display_name' => 'View Helpdesk Tickets', 'group' => 'helpdesk_tickets'],
            ['name' => 'add_helpdesk_tickets', 'display_name' => 'Add Helpdesk Tickets', 'group' => 'helpdesk_tickets'],
            ['name' => 'edit_helpdesk_tickets', 'display_name' => 'Edit Helpdesk Tickets', 'group' => 'helpdesk_tickets'],
            ['name' => 'delete_helpdesk_tickets', 'display_name' => 'Delete Helpdesk Tickets', 'group' => 'helpdesk_tickets'],
            ['name' => 'restore_helpdesk_tickets', 'display_name' => 'Restore Helpdesk Tickets', 'group' => 'helpdesk_tickets'],
        ];

        foreach ($permissions as $p) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $p['name']],
                [
                    'display_name' => $p['display_name'],
                    'guard_name' => 'web',
                    'group' => $p['group'],
                    'module_slug' => 'helpdesk',
                    'created_by' => 1,
                    'updated_by' => 1,
                ],
            );

            $this->command->info('Seeded permission: '.$permission->name);
        }

        $admin = Role::query()->where('name', 'administrator')->first();

        if (! $admin) {
            $this->command->warn('Administrator role not found. Skipping Helpdesk permission assignment.');

            return;
        }

        $helpdeskPermissionIds = Permission::query()
            ->where('module_slug', 'helpdesk')
            ->pluck('id')
            ->toArray();

        if (empty($helpdeskPermissionIds)) {
            $this->command->warn('No Helpdesk permissions found to assign to administrator.');

            return;
        }

        $admin->permissions()->syncWithoutDetaching($helpdeskPermissionIds);
        $this->command->info('Assigned Helpdesk permissions to administrator role.');
    }
}
