import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import RoleForm from '@/components/roles/role-form';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem, RoleEditPageProps } from '@/types';

export default function RolesEdit({
    role,
    initialValues,
    permissionGroups,
}: RoleEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Roles',
            href: RoleController.index(),
        },
        {
            title: role.display_name,
            href: RoleController.edit(role.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${role.display_name}`}
            description="Update the role label, guidance, and permission bundle without changing module behavior accidentally."
        >
            <Head title={`Edit ${role.display_name}`} />
            <RoleForm
                mode="edit"
                role={role}
                initialValues={initialValues}
                permissionGroups={permissionGroups}
            />
        </AppLayout>
    );
}
