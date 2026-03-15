import ManagedUserForm from '@/components/users/managed-user-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { UserEditPageProps } from '@/types/user-management';

export default function UsersEdit({
    user,
    initialValues,
    availableRoles,
    statusOptions,
    genderOptions,
}: UserEditPageProps) {
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
            title: user.name,
            href: route('app.users.edit', user.id),
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
                statusOptions={statusOptions}
                genderOptions={genderOptions}
            />
        </AppLayout>
    );
}
