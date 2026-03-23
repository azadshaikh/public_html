<?php

namespace Modules\Customers\Database\Seeders;

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
            // Customers
            ['name' => 'view_customers', 'display_name' => 'View Customers', 'group' => 'customers'],
            ['name' => 'add_customers', 'display_name' => 'Add Customers', 'group' => 'customers'],
            ['name' => 'edit_customers', 'display_name' => 'Edit Customers', 'group' => 'customers'],
            ['name' => 'delete_customers', 'display_name' => 'Delete Customers', 'group' => 'customers'],
            ['name' => 'restore_customers', 'display_name' => 'Restore Customers', 'group' => 'customers'],

            // Customer Contacts
            ['name' => 'view_customer_contacts', 'display_name' => 'View Customer Contacts', 'group' => 'customer_contacts'],
            ['name' => 'add_customer_contacts', 'display_name' => 'Add Customer Contacts', 'group' => 'customer_contacts'],
            ['name' => 'edit_customer_contacts', 'display_name' => 'Edit Customer Contacts', 'group' => 'customer_contacts'],
            ['name' => 'delete_customer_contacts', 'display_name' => 'Delete Customer Contacts', 'group' => 'customer_contacts'],
            ['name' => 'restore_customer_contacts', 'display_name' => 'Restore Customer Contacts', 'group' => 'customer_contacts'],
        ];

        foreach ($permissions as $p) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $p['name']],
                [
                    'display_name' => $p['display_name'],
                    'guard_name' => 'web',
                    'group' => $p['group'],
                    'module_slug' => 'customers',
                    'created_by' => 1,
                    'updated_by' => 1,
                ],
            );

            $this->command->info('Seeded permission: '.$permission->name);
        }

        $admin = Role::query()->where('name', 'administrator')->first();

        if (! $admin) {
            $this->command->warn('Administrator role not found. Skipping Customers permission assignment.');

            return;
        }

        $customerPermissionIds = Permission::query()
            ->where('module_slug', 'customers')
            ->pluck('id')
            ->toArray();

        if (empty($customerPermissionIds)) {
            $this->command->warn('No Customers permissions found to assign to administrator.');

            return;
        }

        $admin->permissions()->syncWithoutDetaching($customerPermissionIds);
        $this->command->info('Assigned Customers permissions to administrator role.');
    }
}
