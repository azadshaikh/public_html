import { Link } from '@inertiajs/react';
import RoleForm from '@/components/roles/role-form';
import { Button } from '@/components/ui/button';
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
    statusOptions,
    permissionGroups,
}: RoleFormPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Role"
            description="Add a new role and assign permissions to it."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.roles.index')}>← Back</Link>
                </Button>
            }
        >
            <RoleForm
                mode="create"
                initialValues={initialValues}
                statusOptions={statusOptions}
                permissionGroups={permissionGroups}
            />
        </AppLayout>
    );
}
