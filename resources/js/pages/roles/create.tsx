import RoleForm from '@/components/roles/role-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, RoleFormPageProps } from '@/types';

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
        title: 'New role',
        href: route('app.roles.create'),
    },
];

export default function RolesCreate({
    initialValues,
    permissionGroups,
}: RoleFormPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create role"
            description="Add a reusable permission bundle for the workflows you are migrating next."
        >
            <RoleForm
                mode="create"
                initialValues={initialValues}
                permissionGroups={permissionGroups}
            />
        </AppLayout>
    );
}
