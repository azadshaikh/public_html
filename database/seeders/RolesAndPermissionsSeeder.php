<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            [
                'name' => 'view_dashboard',
                'display_name' => 'View Dashboard',
                'group' => 'dashboard',
                'module_slug' => 'application',
                'description' => 'Access the application dashboard.',
            ],
            [
                'name' => 'view_roles',
                'display_name' => 'View Roles',
                'group' => 'roles',
                'module_slug' => 'application',
                'description' => 'View the roles index and details.',
            ],
            [
                'name' => 'add_roles',
                'display_name' => 'Add Roles',
                'group' => 'roles',
                'module_slug' => 'application',
                'description' => 'Create new roles.',
            ],
            [
                'name' => 'edit_roles',
                'display_name' => 'Edit Roles',
                'group' => 'roles',
                'module_slug' => 'application',
                'description' => 'Update existing roles and their permission assignments.',
            ],
            [
                'name' => 'delete_roles',
                'display_name' => 'Delete Roles',
                'group' => 'roles',
                'module_slug' => 'application',
                'description' => 'Delete roles that are no longer needed.',
            ],
            [
                'name' => 'view_users',
                'display_name' => 'View Users',
                'group' => 'users',
                'module_slug' => 'application',
                'description' => 'View users and their assigned roles.',
            ],
            [
                'name' => 'edit_users',
                'display_name' => 'Edit Users',
                'group' => 'users',
                'module_slug' => 'application',
                'description' => 'Edit users and manage role assignments.',
            ],
            [
                'name' => 'manage_modules',
                'display_name' => 'Manage Modules',
                'group' => 'system',
                'module_slug' => 'application',
                'description' => 'Enable and disable application modules.',
            ],
        ])->map(function (array $permission): Permission {
            return Permission::query()->updateOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => 'web',
                ],
                $permission,
            );
        });

        $roles = [
            [
                'name' => Role::SUPER_USER,
                'display_name' => 'Super User',
                'description' => 'Bypasses permission checks and can access all application features.',
            ],
            [
                'name' => 'administrator',
                'display_name' => 'Administrator',
                'description' => 'Full administrative access to application-managed features.',
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Operational access for internal management workflows.',
            ],
            [
                'name' => 'staff',
                'display_name' => 'Staff',
                'description' => 'Standard internal staff access.',
            ],
            [
                'name' => 'customer',
                'display_name' => 'Customer',
                'description' => 'Customer-facing account access.',
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Baseline authenticated application user.',
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::query()->updateOrCreate(
                [
                    'name' => $roleData['name'],
                    'guard_name' => 'web',
                ],
                [
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'],
                    'is_system' => true,
                ],
            );

            if ($role->name === 'administrator') {
                $role->syncPermissions($permissions);

                continue;
            }

            if ($role->name === Role::SUPER_USER) {
                $role->syncPermissions([]);

                continue;
            }

            $dashboardPermission = $permissions->firstWhere('name', 'view_dashboard');

            $role->syncPermissions($dashboardPermission instanceof Permission ? [$dashboardPermission] : []);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
