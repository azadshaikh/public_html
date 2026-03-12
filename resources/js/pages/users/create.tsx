import UserController from '@/actions/App/Http/Controllers/UserController';
import ManagedUserForm from '@/components/users/managed-user-form';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import type { UserCreatePageProps } from '@/types/user-management';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Users',
        href: UserController.index(),
    },
    {
        title: 'New user',
        href: UserController.create(),
    },
];

export default function UsersCreate({
    initialValues,
    availableRoles,
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
            />
        </AppLayout>
    );
}
