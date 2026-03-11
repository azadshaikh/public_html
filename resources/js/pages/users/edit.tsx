import { Head } from '@inertiajs/react';
import ManagedUserController from '@/actions/App/Http/Controllers/ManagedUserController';
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
            href: ManagedUserController.index(),
        },
        {
            title: user.name,
            href: ManagedUserController.edit(user.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${user.name}`}
            description="Adjust account details and role assignments without touching password or ownership data."
        >
            <Head title={`Edit ${user.name}`} />
            <ManagedUserForm
                user={user}
                initialValues={initialValues}
                availableRoles={availableRoles}
            />
        </AppLayout>
    );
}
