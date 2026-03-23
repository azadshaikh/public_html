<?php

namespace Modules\Subscriptions\Database\Seeders;

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
            // Plans
            ['name' => 'view_plans', 'display_name' => 'View Plans', 'group' => 'plans'],
            ['name' => 'add_plans', 'display_name' => 'Add Plans', 'group' => 'plans'],
            ['name' => 'edit_plans', 'display_name' => 'Edit Plans', 'group' => 'plans'],
            ['name' => 'delete_plans', 'display_name' => 'Delete Plans', 'group' => 'plans'],
            ['name' => 'restore_plans', 'display_name' => 'Restore Plans', 'group' => 'plans'],

            // Subscriptions
            ['name' => 'view_subscriptions', 'display_name' => 'View Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'add_subscriptions', 'display_name' => 'Add Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'edit_subscriptions', 'display_name' => 'Edit Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'delete_subscriptions', 'display_name' => 'Delete Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'restore_subscriptions', 'display_name' => 'Restore Subscriptions', 'group' => 'subscriptions'],

            // Subscription lifecycle
            ['name' => 'cancel_subscriptions', 'display_name' => 'Cancel Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'resume_subscriptions', 'display_name' => 'Resume Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'pause_subscriptions', 'display_name' => 'Pause Subscriptions', 'group' => 'subscriptions'],
        ];

        foreach ($permissions as $p) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $p['name']],
                [
                    'display_name' => $p['display_name'],
                    'guard_name' => 'web',
                    'group' => $p['group'],
                    'module_slug' => 'subscriptions',
                    'created_by' => 1,
                    'updated_by' => 1,
                ],
            );

            $this->command->info('Seeded permission: '.$permission->name);
        }

        $admin = Role::query()->where('name', 'administrator')->first();

        if (! $admin) {
            $this->command->warn('Administrator role not found. Skipping Subscriptions permission assignment.');

            return;
        }

        $subscriptionsPermissionIds = Permission::query()
            ->where('module_slug', 'subscriptions')
            ->pluck('id')
            ->toArray();

        if (empty($subscriptionsPermissionIds)) {
            $this->command->warn('No Subscriptions permissions found to assign to administrator.');

            return;
        }

        $admin->permissions()->syncWithoutDetaching($subscriptionsPermissionIds);
        $this->command->info('Assigned Subscriptions permissions to administrator role.');
    }
}
