import ManagedUserForm from '@/components/users/managed-user-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { UserCreatePageProps } from '@/types/user-management';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
    },
    {
        title: 'Users',
        href: route('app.users.index'),
    },
    {
        title: 'New user',
        href: route('app.users.create'),
    },
];

export default function UsersCreate({
    initialValues,
    availableRoles,
    statusOptions,
}: UserCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create user"
            description="Provision a new managed account before more migrated features depend on it."
        >
            <ManagedUserForm
                mode="create"
                initialValues={initialValues}
                availableRoles={availableRoles}
                statusOptions={statusOptions}
            />
        </AppLayout>
    );
}
