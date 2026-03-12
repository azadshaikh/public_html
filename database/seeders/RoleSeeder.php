<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $roles = $this->getDefaultRoles();

        foreach ($roles as $roleData) {
            DB::table('roles')->updateOrInsert(
                [
                    'name' => $roleData['name'],
                    'guard_name' => $roleData['guard_name'],
                ],
                [
                    'id' => $roleData['id'],
                    'display_name' => $roleData['display_name'],
                    'status' => 'active',
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ],
            );

            $roleId = (int) DB::table('roles')
                ->where('name', $roleData['name'])
                ->where('guard_name', $roleData['guard_name'])
                ->value('id');

            $this->assignPermissionsToRole($roleId, $roleData['name']);

            $this->command->info('Seeded role: '.$roleData['name']);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('roles', 'id'), COALESCE((SELECT MAX(id) FROM roles), 1))");
        }
    }

    /**
     * Get the default roles configuration.
     */
    private function getDefaultRoles(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'super_user',
                'display_name' => 'Super User',
                'guard_name' => 'web',
            ],
            [
                'id' => 2,
                'name' => 'administrator',
                'display_name' => 'Administrator',
                'guard_name' => 'web',
            ],
            [
                'id' => 3,
                'name' => 'manager',
                'display_name' => 'Manager',
                'guard_name' => 'web',
            ],
            [
                'id' => 4,
                'name' => 'customer',
                'display_name' => 'Customer',
                'guard_name' => 'web',
            ],
            [
                'id' => 5,
                'name' => 'staff',
                'display_name' => 'Staff',
                'guard_name' => 'web',
            ],
            [
                'id' => 6,
                'name' => 'user',
                'display_name' => 'User',
                'guard_name' => 'web',
            ],
        ];
    }

    /**
     * Assign appropriate permissions to a role based on its name.
     */
    private function assignPermissionsToRole(int $roleId, string $roleName): void
    {
        switch ($roleName) {
            case 'super_user':
                DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
                break;

            case 'administrator':
                $this->syncPermissions($roleId, DB::table('permissions')->pluck('id')->all());
                $this->command->info('Assigned all permissions to administrator role');
                break;

            case 'manager':
                $this->syncPermissions($roleId, $this->permissionIds([
                    'view_dashboard',
                    'view_users',
                    'add_users',
                    'edit_users',
                    'view_todos',
                    'add_todos',
                    'edit_todos',
                    'delete_todos',
                ]));
                $this->command->info('Assigned manager permissions to role');
                break;

            case 'staff':
                $this->syncPermissions($roleId, $this->permissionIds([
                    'view_dashboard',
                    'view_todos',
                    'add_todos',
                    'edit_todos',
                    'delete_todos',
                ]));
                $this->command->info('Assigned staff permissions to role');
                break;

            case 'customer':
                $this->syncPermissions($roleId, $this->permissionIds([
                    'view_dashboard',
                    'view_customers',
                    'add_customers',
                    'edit_customers',
                    'delete_customers',
                    'restore_customers',
                    'view_customer_contacts',
                    'add_customer_contacts',
                    'edit_customer_contacts',
                    'delete_customer_contacts',
                    'restore_customer_contacts',
                ]));
                $this->command->info('Assigned customer permissions to role');
                break;

            case 'user':
                $this->syncPermissions($roleId, $this->permissionIds([
                    'view_dashboard',
                ]));
                $this->command->info('Assigned baseline permissions to user role');
                break;

            default:
                $this->command->warn('No permission assignment defined for role: '.$roleName);
                break;
        }
    }

    /**
     * Get permission ids for the supplied names.
     */
    private function permissionIds(array $names): array
    {
        return DB::table('permissions')
            ->whereIn('name', $names)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Sync role permissions using the pivot table directly.
     */
    private function syncPermissions(int $roleId, array $permissionIds): void
    {
        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();

        if ($permissionIds === []) {
            return;
        }

        DB::table('role_has_permissions')->insert(
            collect($permissionIds)
                ->unique()
                ->values()
                ->map(fn (int $permissionId): array => [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ])
                ->all(),
        );
    }
}
