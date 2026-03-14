<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $permissions = [
            [
                'name' => 'view_users',
                'display_name' => 'View Users',
                'group' => 'users',
                'module_slug' => 'application',
            ],
            [
                'name' => 'add_users',
                'display_name' => 'Add Users',
                'group' => 'users',
                'module_slug' => 'application',
            ],
            [
                'name' => 'edit_users',
                'display_name' => 'Edit Users',
                'group' => 'users',
                'module_slug' => 'application',
            ],
            [
                'name' => 'delete_users',
                'display_name' => 'Delete Users',
                'group' => 'users',
                'module_slug' => 'application',
            ],
            [
                'name' => 'restore_users',
                'display_name' => 'Restore Users',
                'group' => 'users',
                'module_slug' => 'application',
            ],
            [
                'name' => 'impersonate_users',
                'display_name' => 'Impersonate Users',
                'group' => 'users',
                'module_slug' => 'application',
            ],
            [
                'name' => 'view_roles',
                'display_name' => 'View Roles',
                'group' => 'roles',
                'module_slug' => 'application',
            ],
            [
                'name' => 'add_roles',
                'display_name' => 'Add Roles',
                'group' => 'roles',
                'module_slug' => 'application',
            ],
            [
                'name' => 'edit_roles',
                'display_name' => 'Edit Roles',
                'group' => 'roles',
                'module_slug' => 'application',
            ],
            [
                'name' => 'delete_roles',
                'display_name' => 'Delete Roles',
                'group' => 'roles',
                'module_slug' => 'application',
            ],
            [
                'name' => 'restore_roles',
                'display_name' => 'Restore Roles',
                'group' => 'roles',
                'module_slug' => 'application',
            ],
            [
                'name' => 'view_dashboard',
                'display_name' => 'View Dashboard',
                'group' => 'dashboard',
                'module_slug' => 'application',
            ],
            // Media Permissions
            [
                'name' => 'view_media',
                'display_name' => 'View Media',
                'group' => 'media',
                'module_slug' => 'application',
            ],
            [
                'name' => 'add_media',
                'display_name' => 'Add Media',
                'group' => 'media',
                'module_slug' => 'application',
            ],
            [
                'name' => 'edit_media',
                'display_name' => 'Edit Media',
                'group' => 'media',
                'module_slug' => 'application',
            ],
            [
                'name' => 'delete_media',
                'display_name' => 'Delete Media',
                'group' => 'media',
                'module_slug' => 'application',
            ],
            [
                'name' => 'restore_media',
                'display_name' => 'Restore Media',
                'group' => 'media',
                'module_slug' => 'application',
            ],
            // System Settings Permissions
            [
                'name' => 'manage_system_settings',
                'display_name' => 'Manage System Settings',
                'group' => 'settings',
                'module_slug' => 'application',
            ],
            // Activity Logs Permissions
            [
                'name' => 'view_activity_logs',
                'display_name' => 'View Activity Logs',
                'group' => 'activity_logs',
                'module_slug' => 'application',
            ],
            [
                'name' => 'delete_activity_logs',
                'display_name' => 'Delete Activity Logs',
                'group' => 'activity_logs',
                'module_slug' => 'application',
            ],
            [
                'name' => 'manage_activity_logs',
                'display_name' => 'Manage Activity Logs',
                'group' => 'activity_logs',
                'module_slug' => 'application',
            ],
            // Login Attempts Permissions
            [
                'name' => 'view_login_attempts',
                'display_name' => 'View Login Attempts',
                'group' => 'login_attempts',
                'module_slug' => 'application',
            ],
            [
                'name' => 'delete_login_attempts',
                'display_name' => 'Delete Login Attempts',
                'group' => 'login_attempts',
                'module_slug' => 'application',
            ],
            [
                'name' => 'manage_login_attempts',
                'display_name' => 'Manage Login Attempts',
                'group' => 'login_attempts',
                'module_slug' => 'application',
            ],
            // 404 Logs permissions
            [
                'name' => 'view_not_found_logs',
                'display_name' => 'View 404 Logs',
                'group' => 'not_found_logs',
                'module_slug' => 'application',
            ],
            [
                'name' => 'delete_not_found_logs',
                'display_name' => 'Delete 404 Logs',
                'group' => 'not_found_logs',
                'module_slug' => 'application',
            ],
            [
                'name' => 'manage_not_found_logs',
                'display_name' => 'Manage 404 Logs',
                'group' => 'not_found_logs',
                'module_slug' => 'application',
            ],
        ];

        foreach ($permissions as $permissionData) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permissionData['name'],
                    'guard_name' => 'web',
                ],
                [
                    'display_name' => $permissionData['display_name'],
                    'group' => $permissionData['group'],
                    'module_slug' => $permissionData['module_slug'],
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ],
            );

            $this->command->info('Seeded permission: '.$permissionData['name']);
        }

        $this->command->info('Permissions seeding completed!');
    }
}
