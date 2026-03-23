<?php

namespace Modules\Billing\Database\Seeders;

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
            // Invoices
            ['name' => 'view_invoices', 'display_name' => 'View Invoices', 'group' => 'invoices'],
            ['name' => 'add_invoices', 'display_name' => 'Add Invoices', 'group' => 'invoices'],
            ['name' => 'edit_invoices', 'display_name' => 'Edit Invoices', 'group' => 'invoices'],
            ['name' => 'delete_invoices', 'display_name' => 'Delete Invoices', 'group' => 'invoices'],
            ['name' => 'restore_invoices', 'display_name' => 'Restore Invoices', 'group' => 'invoices'],

            // Payments
            ['name' => 'view_payments', 'display_name' => 'View Payments', 'group' => 'payments'],
            ['name' => 'add_payments', 'display_name' => 'Add Payments', 'group' => 'payments'],
            ['name' => 'edit_payments', 'display_name' => 'Edit Payments', 'group' => 'payments'],
            ['name' => 'delete_payments', 'display_name' => 'Delete Payments', 'group' => 'payments'],
            ['name' => 'restore_payments', 'display_name' => 'Restore Payments', 'group' => 'payments'],

            // Credits
            ['name' => 'view_credits', 'display_name' => 'View Credits', 'group' => 'credits'],
            ['name' => 'add_credits', 'display_name' => 'Add Credits', 'group' => 'credits'],
            ['name' => 'edit_credits', 'display_name' => 'Edit Credits', 'group' => 'credits'],
            ['name' => 'delete_credits', 'display_name' => 'Delete Credits', 'group' => 'credits'],
            ['name' => 'restore_credits', 'display_name' => 'Restore Credits', 'group' => 'credits'],

            // Refunds
            ['name' => 'view_refunds', 'display_name' => 'View Refunds', 'group' => 'refunds'],
            ['name' => 'add_refunds', 'display_name' => 'Add Refunds', 'group' => 'refunds'],
            ['name' => 'edit_refunds', 'display_name' => 'Edit Refunds', 'group' => 'refunds'],
            ['name' => 'delete_refunds', 'display_name' => 'Delete Refunds', 'group' => 'refunds'],
            ['name' => 'restore_refunds', 'display_name' => 'Restore Refunds', 'group' => 'refunds'],

            // Taxes
            ['name' => 'view_taxes', 'display_name' => 'View Taxes', 'group' => 'taxes'],
            ['name' => 'add_taxes', 'display_name' => 'Add Taxes', 'group' => 'taxes'],
            ['name' => 'edit_taxes', 'display_name' => 'Edit Taxes', 'group' => 'taxes'],
            ['name' => 'delete_taxes', 'display_name' => 'Delete Taxes', 'group' => 'taxes'],
            ['name' => 'restore_taxes', 'display_name' => 'Restore Taxes', 'group' => 'taxes'],

            // Coupons
            ['name' => 'view_coupons', 'display_name' => 'View Coupons', 'group' => 'coupons'],
            ['name' => 'add_coupons', 'display_name' => 'Add Coupons', 'group' => 'coupons'],
            ['name' => 'edit_coupons', 'display_name' => 'Edit Coupons', 'group' => 'coupons'],
            ['name' => 'delete_coupons', 'display_name' => 'Delete Coupons', 'group' => 'coupons'],
            ['name' => 'restore_coupons', 'display_name' => 'Restore Coupons', 'group' => 'coupons'],

            // Transactions (read-only)
            ['name' => 'view_transactions', 'display_name' => 'View Transactions', 'group' => 'transactions'],

            // Settings
            ['name' => 'manage_billing_settings', 'display_name' => 'Manage Billing Settings', 'group' => 'billing_settings'],
        ];

        foreach ($permissions as $p) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $p['name']],
                [
                    'display_name' => $p['display_name'],
                    'guard_name' => 'web',
                    'group' => $p['group'],
                    'module_slug' => 'billing',
                    'created_by' => 1,
                    'updated_by' => 1,
                ],
            );

            $this->command->info('Seeded permission: '.$permission->name);
        }

        $admin = Role::query()->where('name', 'administrator')->first();

        if (! $admin) {
            $this->command->warn('Administrator role not found. Skipping Billing permission assignment.');

            return;
        }

        $billingPermissionIds = Permission::query()
            ->where('module_slug', 'billing')
            ->pluck('id')
            ->toArray();

        if (empty($billingPermissionIds)) {
            $this->command->warn('No Billing permissions found to assign to administrator.');

            return;
        }

        $admin->permissions()->syncWithoutDetaching($billingPermissionIds);
        $this->command->info('Assigned Billing permissions to administrator role.');
    }
}
