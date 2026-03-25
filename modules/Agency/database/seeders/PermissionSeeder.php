<?php

declare(strict_types=1);

namespace Modules\Agency\Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'manage_agency_settings', 'display_name' => 'Manage Agency Settings', 'group' => 'settings'],
            ['name' => 'view_agency_websites', 'display_name' => 'View Agency Websites', 'group' => 'websites'],
            ['name' => 'add_agency_websites', 'display_name' => 'Add Agency Websites', 'group' => 'websites'],
            ['name' => 'edit_agency_websites', 'display_name' => 'Edit Agency Websites', 'group' => 'websites'],
            ['name' => 'delete_agency_websites', 'display_name' => 'Delete Agency Websites', 'group' => 'websites'],
            ['name' => 'restore_agency_websites', 'display_name' => 'Restore Agency Websites', 'group' => 'websites'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::query()->updateOrCreate(
                ['name' => $permissionData['name']],
                [
                    'display_name' => $permissionData['display_name'],
                    'guard_name' => 'web',
                    'group' => $permissionData['group'],
                    'module_slug' => 'agency',
                    'created_by' => 1,
                    'updated_by' => 1,
                ],
            );
        }

        $administrator = Role::query()->where('name', 'administrator')->first();

        if (! $administrator) {
            return;
        }

        $permissionIds = Permission::query()
            ->where('module_slug', 'agency')
            ->pluck('id')
            ->toArray();

        if ($permissionIds !== []) {
            $administrator->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
