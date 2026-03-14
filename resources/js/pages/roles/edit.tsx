import RoleForm from '@/components/roles/role-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, RoleEditPageProps } from '@/types';

export default function RolesEdit({
    role,
    initialValues,
    statusOptions,
    permissionGroups,
}: RoleEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: route('dashboard'),
        },
        {
            title: 'Roles',
            href: route('app.roles.index'),
        },
        {
            title: role.display_name,
            href: route('app.roles.edit', role.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${role.display_name}`}
            description="Update the role label, guidance, and permission bundle without changing module behavior accidentally."
        >
            <RoleForm
                mode="edit"
                role={role}
                initialValues={initialValues}
                statusOptions={statusOptions}
                permissionGroups={permissionGroups}
            />
        </AppLayout>
    );
}
