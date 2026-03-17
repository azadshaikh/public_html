<?php

namespace Modules\CMS\Database\Seeders;

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
            [
                'name' => 'view_cms_forms',
                'display_name' => 'View Forms',
                'group' => 'forms',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_cms_forms',
                'display_name' => 'Add Forms',
                'group' => 'forms',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_cms_forms',
                'display_name' => 'Edit Forms',
                'group' => 'forms',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_cms_forms',
                'display_name' => 'Delete Forms',
                'group' => 'forms',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'restore_cms_forms',
                'display_name' => 'Restore Forms',
                'group' => 'forms',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_design_blocks',
                'display_name' => 'View Design Blocks',
                'group' => 'design_blocks',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_design_blocks',
                'display_name' => 'Add Design Blocks',
                'group' => 'design_blocks',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_design_blocks',
                'display_name' => 'Edit Design Blocks',
                'group' => 'design_blocks',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_design_blocks',
                'display_name' => 'Delete Design Blocks',
                'group' => 'design_blocks',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'restore_design_blocks',
                'display_name' => 'Restore Design Blocks',
                'group' => 'design_blocks',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_menus',
                'display_name' => 'View Menus',
                'group' => 'menus',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_menus',
                'display_name' => 'Add Menus',
                'group' => 'menus',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_menus',
                'display_name' => 'Edit Menus',
                'group' => 'menus',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_menus',
                'display_name' => 'Delete Menus',
                'group' => 'menus',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'restore_menus',
                'display_name' => 'Restore Menus',
                'group' => 'menus',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_themes',
                'display_name' => 'View Themes',
                'group' => 'themes',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_themes',
                'display_name' => 'Add Themes',
                'group' => 'themes',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_themes',
                'display_name' => 'Edit Themes',
                'group' => 'themes',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_themes',
                'display_name' => 'Delete Themes',
                'group' => 'themes',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_widgets',
                'display_name' => 'View Widgets',
                'group' => 'widgets',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_widgets',
                'display_name' => 'Edit Widgets',
                'group' => 'widgets',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'manage_default_pages',
                'display_name' => 'Manage Default Pages',
                'group' => 'settings',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_posts',
                'display_name' => 'View Posts',
                'group' => 'posts',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_posts',
                'display_name' => 'Add Posts',
                'group' => 'posts',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_posts',
                'display_name' => 'Edit Posts',
                'group' => 'posts',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_posts',
                'display_name' => 'Delete Posts',
                'group' => 'posts',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'restore_posts',
                'display_name' => 'Restore Posts',
                'group' => 'posts',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'duplicate_posts',
                'display_name' => 'Duplicate Posts',
                'group' => 'posts',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_pages',
                'display_name' => 'View Pages',
                'group' => 'pages',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_pages',
                'display_name' => 'Add Pages',
                'group' => 'pages',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_pages',
                'display_name' => 'Edit Pages',
                'group' => 'pages',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_pages',
                'display_name' => 'Delete Pages',
                'group' => 'pages',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'restore_pages',
                'display_name' => 'Restore Pages',
                'group' => 'pages',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'duplicate_pages',
                'display_name' => 'Duplicate Pages',
                'group' => 'pages',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_tags',
                'display_name' => 'View Tags',
                'group' => 'tags',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_tags',
                'display_name' => 'Add Tags',
                'group' => 'tags',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_tags',
                'display_name' => 'Edit Tags',
                'group' => 'tags',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_tags',
                'display_name' => 'Delete Tags',
                'group' => 'tags',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'restore_tags',
                'display_name' => 'Restore Tags',
                'group' => 'tags',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_categories',
                'display_name' => 'View Categories',
                'group' => 'categories',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_categories',
                'display_name' => 'Add Categories',
                'group' => 'categories',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_categories',
                'display_name' => 'Edit Categories',
                'group' => 'categories',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_categories',
                'display_name' => 'Delete Categories',
                'group' => 'categories',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'restore_categories',
                'display_name' => 'Restore Categories',
                'group' => 'categories',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_comments',
                'display_name' => 'View Comments',
                'group' => 'comments',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_comments',
                'display_name' => 'Add Comments',
                'group' => 'comments',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_comments',
                'display_name' => 'Edit Comments',
                'group' => 'comments',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_comments',
                'display_name' => 'Delete Comments',
                'group' => 'comments',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'restore_comments',
                'display_name' => 'Restore Comments',
                'group' => 'comments',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'manage_seo_settings',
                'display_name' => 'Manage SEO Settings',
                'group' => 'seo_settings',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'manage_integrations_seo_settings',
                'display_name' => 'Manage Integrations SEO Settings',
                'group' => 'seo_settings',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'manage_cms_seo_settings',
                'display_name' => 'Manage CMS SEO Settings',
                'group' => 'seo_settings',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'view_redirections',
                'display_name' => 'View SEO Redirections',
                'group' => 'redirections',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'add_redirections',
                'display_name' => 'Add SEO Redirections',
                'group' => 'redirections',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'edit_redirections',
                'display_name' => 'Edit SEO Redirections',
                'group' => 'redirections',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'delete_redirections',
                'display_name' => 'Delete SEO Redirections',
                'group' => 'redirections',
                'module_slug' => 'cms',
            ],
            [
                'name' => 'preview_unpublished_content',
                'display_name' => 'Preview Unpublished Content',
                'group' => 'content_preview',
                'module_slug' => 'cms',
            ],
        ];

        foreach ($permissions as $permissionData) {
            $existingPermission = Permission::query()->where('name', $permissionData['name'])->first();

            if (! $existingPermission) {
                $permission = Permission::create([
                    'name' => $permissionData['name'],
                    'display_name' => $permissionData['display_name'],
                    'guard_name' => 'web',
                    'group' => $permissionData['group'],
                    'module_slug' => $permissionData['module_slug'],
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);

                $this->command->info('Created CMS permission: '.$permission->name);
            } else {
                $existingPermission->update([
                    'display_name' => $permissionData['display_name'],
                    'group' => $permissionData['group'],
                    'module_slug' => $permissionData['module_slug'],
                    'updated_by' => 1,
                ]);

                $this->command->info('Updated CMS permission: '.$existingPermission->name);
            }
        }

        $this->command->info('CMS permissions seeding completed!');

        $this->assignPermissionsToAdministrator();
    }

    /**
     * Assign all CMS permissions to the administrator role
     */
    private function assignPermissionsToAdministrator(): void
    {
        $administratorRole = Role::query()->where('name', 'administrator')->first();

        if (! $administratorRole) {
            $this->command->warn('Administrator role not found. Skipping permission assignment.');

            return;
        }

        $cmsPermissions = Permission::query()->where('module_slug', 'cms')->get();

        if ($cmsPermissions->isEmpty()) {
            $this->command->warn('No CMS permissions found to assign.');

            return;
        }

        $administratorRole->permissions()->syncWithoutDetaching($cmsPermissions->pluck('id')->toArray());

        $this->command->info(sprintf('Assigned %s CMS permissions to administrator role.', $cmsPermissions->count()));
    }
}
