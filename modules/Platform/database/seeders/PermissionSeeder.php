<?php

namespace Modules\Platform\Database\Seeders;

use App\Models\Permission;
use App\Models\Role;

class PermissionSeeder extends PlatformSeeder
{
    public function run(): void
    {
        $auditColumns = $this->auditColumns();

        foreach ($this->permissionDefinitions() as $definition) {
            $permission = Permission::query()->updateOrCreate(
                [
                    'name' => $definition['name'],
                    'guard_name' => 'web',
                ],
                [
                    'display_name' => $definition['display_name'],
                    'group' => $definition['group'],
                    'module_slug' => 'platform',
                    ...$auditColumns,
                ],
            );

            $this->writeInfo('Seeded Platform permission: '.$permission->name);
        }

        $administratorRole = Role::query()
            ->where('name', 'administrator')
            ->where('guard_name', 'web')
            ->first();

        if (! $administratorRole instanceof Role) {
            $this->writeWarning('Administrator role not found. Skipping Platform permission assignment.');

            return;
        }

        $permissionIds = Permission::query()
            ->where('module_slug', 'platform')
            ->pluck('id')
            ->all();

        if ($permissionIds === []) {
            $this->writeWarning('No Platform permissions were available to assign.');

            return;
        }

        $administratorRole->permissions()->syncWithoutDetaching($permissionIds);

        $this->writeInfo('Assigned Platform permissions to administrator role.');
    }

    /**
     * @return array<int, array{name: string, display_name: string, group: string}>
     */
    private function permissionDefinitions(): array
    {
        $resources = [
            ['slug' => 'agencies', 'label' => 'Agencies', 'group' => 'platform_agencies'],
            ['slug' => 'servers', 'label' => 'Servers', 'group' => 'platform_servers'],
            ['slug' => 'websites', 'label' => 'Websites', 'group' => 'platform_websites'],
            ['slug' => 'domains', 'label' => 'Domains', 'group' => 'platform_domains'],
            ['slug' => 'providers', 'label' => 'Providers', 'group' => 'platform_providers'],
            ['slug' => 'secrets', 'label' => 'Secrets', 'group' => 'platform_secrets'],
            ['slug' => 'tlds', 'label' => 'TLDs', 'group' => 'platform_tlds'],
            ['slug' => 'domain_dns_records', 'label' => 'Domain DNS Records', 'group' => 'platform_dns_records'],
            ['slug' => 'domain_accounts', 'label' => 'Domain Accounts', 'group' => 'platform_domain_accounts'],
            ['slug' => 'domain_ssl', 'label' => 'Domain SSL', 'group' => 'platform_domain_ssl'],
            ['slug' => 'domain_registrars', 'label' => 'Domain Registrars', 'group' => 'platform_domain_registrars'],
            ['slug' => 'domain_groups', 'label' => 'Domain Groups', 'group' => 'platform_domain_groups'],
        ];

        $definitions = [];

        foreach ($resources as $resource) {
            foreach (['view' => 'View', 'add' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete', 'restore' => 'Restore'] as $prefix => $actionLabel) {
                $definitions[] = [
                    'name' => $prefix.'_'.$resource['slug'],
                    'display_name' => $actionLabel.' '.$resource['label'],
                    'group' => $resource['group'],
                ];
            }
        }

        $definitions[] = [
            'name' => 'manage_platform_settings',
            'display_name' => 'Manage Platform Settings',
            'group' => 'platform_settings',
        ];

        return $definitions;
    }
}
