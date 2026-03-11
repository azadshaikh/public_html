import RoleController from '@/actions/App/Http/Controllers/RoleController';
import RoleForm from '@/components/roles/role-form';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem, RoleFormPageProps } from '@/types';

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
        title: 'New role',
        href: RoleController.create(),
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
