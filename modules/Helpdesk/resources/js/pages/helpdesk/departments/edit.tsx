import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import DepartmentForm from '../../../components/departments/department-form';
import type { DepartmentEditPageProps } from '../../../types/helpdesk';

export default function DepartmentsEdit({
    department,
    ...props
}: DepartmentEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Departments', href: route('helpdesk.departments.index') },
        {
            title: department.name,
            href: route('helpdesk.departments.show', department.id),
        },
        {
            title: 'Edit',
            href: route('helpdesk.departments.edit', department.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${department.name}`}
            description="Update department details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link
                        href={route(
                            'helpdesk.departments.show',
                            department.id,
                        )}
                    >
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <DepartmentForm
                mode="edit"
                department={department}
                {...props}
            />
        </AppLayout>
    );
}
