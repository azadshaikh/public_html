import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CmsForm from '../../../components/forms/form-form';
import type { FormCreatePageProps } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Forms', href: route('cms.form.index') },
    { title: 'Create', href: route('cms.form.create') },
];

export default function FormsCreate(props: FormCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Form"
            description="Build a new submission form with custom markup, templates, and confirmation rules."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.form.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Forms
                    </Link>
                </Button>
            }
        >
            <CmsForm mode="create" {...props} />
        </AppLayout>
    );
}
