<?php

namespace Modules\ReleaseManager\Database\Seeders;

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
            // Releases Group
            [
                'name' => 'view_releases',
                'display_name' => 'View Releases',
                'group' => 'releases',
            ],
            [
                'name' => 'add_releases',
                'display_name' => 'Add Releases',
                'group' => 'releases',
            ],
            [
                'name' => 'edit_releases',
                'display_name' => 'Edit Releases',
                'group' => 'releases',
            ],
            [
                'name' => 'delete_releases',
                'display_name' => 'Delete Releases',
                'group' => 'releases',
            ],
            [
                'name' => 'restore_releases',
                'display_name' => 'Restore Releases',
                'group' => 'releases',
            ],
        ];

        foreach ($permissions as $permissionData) {
            // Check if permission already exists
            $existingPermission = Permission::query()->where('name', $permissionData['name'])->first();

            if (! $existingPermission) {
                // Create new permission using the model
                $permission = Permission::create([
                    'name' => $permissionData['name'],
                    'display_name' => $permissionData['display_name'],
                    'guard_name' => 'web',
                    'group' => $permissionData['group'],
                    'module_slug' => 'releasemanager',
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);

                $this->command->info('Created ReleaseManager permission: '.$permission->name);
            } else {
                // Update existing permission if needed
                $existingPermission->update([
                    'display_name' => $permissionData['display_name'],
                    'group' => $permissionData['group'],
                    'module_slug' => 'releasemanager',
                    'updated_by' => 1,
                ]);

                $this->command->info('Updated ReleaseManager permission: '.$existingPermission->name);
            }
        }

        $this->command->info('ReleaseManager permissions seeding completed!');

        // Assign all ReleaseManager permissions to administrator role
        $this->assignPermissionsToAdministrator();
    }

    /**
     * Assign all ReleaseManager permissions to the administrator role
     */
    private function assignPermissionsToAdministrator(): void
    {
        // Find the administrator role
        $administratorRole = Role::query()->where('name', 'administrator')->first();

        if (! $administratorRole) {
            $this->command->warn('Administrator role not found. Skipping permission assignment.');

            return;
        }

        // Get all ReleaseManager permissions
        $releaseManagerPermissions = Permission::query()->where('module_slug', 'releasemanager')->get();

        if ($releaseManagerPermissions->isEmpty()) {
            $this->command->warn('No ReleaseManager permissions found to assign.');

            return;
        }

        // Assign permissions to administrator role
        $administratorRole->permissions()->syncWithoutDetaching($releaseManagerPermissions->pluck('id')->toArray());

        $this->command->info(sprintf('Assigned %s ReleaseManager permissions to administrator role.', $releaseManagerPermissions->count()));
    }
}
