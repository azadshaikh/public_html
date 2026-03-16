import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CmsForm from '../../../components/forms/form-form';
import type { FormEditPageProps } from '../../../types/cms';

export default function FormsEdit({ form, ...props }: FormEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Forms', href: route('cms.form.index') },
        {
            title: form.title || 'Form',
            href: route('cms.form.edit', form.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit Form: ${form.title}`}
            description="Update publishing, embed details, and the submission confirmation flow."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.form.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Forms
                    </Link>
                </Button>
            }
        >
            <CmsForm mode="edit" form={form} {...props} />
        </AppLayout>
    );
}
