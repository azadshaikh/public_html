<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @return array<int, array{name: string, display_name: string, group: string, module_slug: string}>
     */
    private function permissions(): array
    {
        return [
            ['name' => 'manage_modules', 'display_name' => 'Manage Modules', 'group' => 'system', 'module_slug' => 'application'],
            ['name' => 'view_addresses', 'display_name' => 'View Addresses', 'group' => 'addresses', 'module_slug' => 'application'],
            ['name' => 'add_addresses', 'display_name' => 'Add Addresses', 'group' => 'addresses', 'module_slug' => 'application'],
            ['name' => 'edit_addresses', 'display_name' => 'Edit Addresses', 'group' => 'addresses', 'module_slug' => 'application'],
            ['name' => 'delete_addresses', 'display_name' => 'Delete Addresses', 'group' => 'addresses', 'module_slug' => 'application'],
            ['name' => 'restore_addresses', 'display_name' => 'Restore Addresses', 'group' => 'addresses', 'module_slug' => 'application'],
            ['name' => 'view_email_providers', 'display_name' => 'View Email Providers', 'group' => 'email_providers', 'module_slug' => 'application'],
            ['name' => 'add_email_providers', 'display_name' => 'Add Email Providers', 'group' => 'email_providers', 'module_slug' => 'application'],
            ['name' => 'edit_email_providers', 'display_name' => 'Edit Email Providers', 'group' => 'email_providers', 'module_slug' => 'application'],
            ['name' => 'delete_email_providers', 'display_name' => 'Delete Email Providers', 'group' => 'email_providers', 'module_slug' => 'application'],
            ['name' => 'restore_email_providers', 'display_name' => 'Restore Email Providers', 'group' => 'email_providers', 'module_slug' => 'application'],
            ['name' => 'view_email_templates', 'display_name' => 'View Email Templates', 'group' => 'email_templates', 'module_slug' => 'application'],
            ['name' => 'add_email_templates', 'display_name' => 'Add Email Templates', 'group' => 'email_templates', 'module_slug' => 'application'],
            ['name' => 'edit_email_templates', 'display_name' => 'Edit Email Templates', 'group' => 'email_templates', 'module_slug' => 'application'],
            ['name' => 'delete_email_templates', 'display_name' => 'Delete Email Templates', 'group' => 'email_templates', 'module_slug' => 'application'],
            ['name' => 'restore_email_templates', 'display_name' => 'Restore Email Templates', 'group' => 'email_templates', 'module_slug' => 'application'],
            ['name' => 'view_email_logs', 'display_name' => 'View Email Logs', 'group' => 'email_logs', 'module_slug' => 'application'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function permissionNames(): array
    {
        return array_column($this->permissions(), 'name');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'];
        $rolePermissionsTable = $tableNames['role_has_permissions'];
        $modelPermissionsTable = $tableNames['model_has_permissions'];

        $permissionIds = DB::table($permissionsTable)
            ->whereIn('name', $this->permissionNames())
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table($rolePermissionsTable)->whereIn('permission_id', $permissionIds)->delete();
            DB::table($modelPermissionsTable)->whereIn('permission_id', $permissionIds)->delete();
            DB::table($permissionsTable)->whereIn('id', $permissionIds)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'];
        $rolesTable = $tableNames['roles'];
        $rolePermissionsTable = $tableNames['role_has_permissions'];
        $now = now();

        foreach ($this->permissions() as $permission) {
            DB::table($permissionsTable)->updateOrInsert(
                [
                    'name' => $permission['name'],
                    'guard_name' => 'web',
                ],
                [
                    'display_name' => $permission['display_name'],
                    'group' => $permission['group'],
                    'module_slug' => $permission['module_slug'],
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ],
            );
        }

        $administratorRoleId = DB::table($rolesTable)
            ->where('name', 'administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId !== null) {
            $permissionIds = DB::table($permissionsTable)
                ->whereIn('name', $this->permissionNames())
                ->pluck('id')
                ->all();

            foreach ($permissionIds as $permissionId) {
                DB::table($rolePermissionsTable)->updateOrInsert(
                    [
                        'permission_id' => $permissionId,
                        'role_id' => $administratorRoleId,
                    ],
                    [
                        'permission_id' => $permissionId,
                        'role_id' => $administratorRoleId,
                    ],
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
