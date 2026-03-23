import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import DepartmentForm from '../../../components/departments/department-form';
import type { DepartmentCreatePageProps } from '../../../types/helpdesk';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Departments', href: route('helpdesk.departments.index') },
    { title: 'Create', href: route('helpdesk.departments.create') },
];

export default function DepartmentsCreate(props: DepartmentCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Department"
            description="Add a new helpdesk department"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('helpdesk.departments.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <DepartmentForm mode="create" {...props} />
        </AppLayout>
    );
}
