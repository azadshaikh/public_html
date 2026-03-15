import ManagedUserForm from '@/components/users/managed-user-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
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
        title: 'Create',
        href: route('app.users.create'),
    },
];

export default function UsersCreate({
    initialValues,
    availableRoles,
    statusOptions,
    genderOptions,
}: UserCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create User"
            description="Add a new user account"
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('app.users.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <ManagedUserForm
                mode="create"
                initialValues={initialValues}
                availableRoles={availableRoles}
                statusOptions={statusOptions}
                genderOptions={genderOptions}
            />
        </AppLayout>
    );
}
