<?php

namespace Modules\Orders\Database\Seeders;

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
            // Orders
            ['name' => 'view_orders', 'display_name' => 'View Orders', 'group' => 'orders'],
            ['name' => 'delete_orders', 'display_name' => 'Delete Orders', 'group' => 'orders'],
            ['name' => 'restore_orders', 'display_name' => 'Restore Orders', 'group' => 'orders'],

            // Settings
            ['name' => 'manage_orders_settings', 'display_name' => 'Manage Orders Settings', 'group' => 'orders_settings'],
        ];

        foreach ($permissions as $p) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $p['name']],
                [
                    'display_name' => $p['display_name'],
                    'guard_name' => 'web',
                    'group' => $p['group'],
                    'module_slug' => 'orders',
                    'created_by' => 1,
                    'updated_by' => 1,
                ],
            );

            $this->command->info('Seeded permission: '.$permission->name);
        }

        $admin = Role::query()->where('name', 'administrator')->first();

        if (! $admin) {
            $this->command->warn('Administrator role not found. Skipping Orders permission assignment.');

            return;
        }

        $ordersPermissionIds = Permission::query()
            ->where('module_slug', 'orders')
            ->pluck('id')
            ->toArray();

        if (empty($ordersPermissionIds)) {
            $this->command->warn('No Orders permissions found to assign to administrator.');

            return;
        }

        $admin->permissions()->syncWithoutDetaching($ordersPermissionIds);
        $this->command->info('Assigned Orders permissions to administrator role.');
    }
}
