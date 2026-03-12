import UserController from '@/actions/App/Http/Controllers/UserController';
import ManagedUserForm from '@/components/users/managed-user-form';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import type { UserEditPageProps } from '@/types/user-management';

export default function UsersEdit({
    user,
    initialValues,
    availableRoles,
}: UserEditPageProps) {
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
            title: user.name,
            href: UserController.edit(user.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${user.name}`}
            description="Adjust account details, role assignments, and credentials without changing account ownership."
        >
            <ManagedUserForm
                mode="edit"
                user={user}
                initialValues={initialValues}
                availableRoles={availableRoles}
            />
        </AppLayout>
    );
}
