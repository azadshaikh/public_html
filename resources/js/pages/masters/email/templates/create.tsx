import TemplateForm from '@/components/email-templates/template-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmailTemplateCreatePageProps } from '@/types/email';

export default function EmailTemplatesCreate({
    initialValues,
    statusOptions,
    providerOptions,
}: EmailTemplateCreatePageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Email Templates',
            href: route('app.masters.email.templates.index'),
        },
        {
            title: 'New template',
            href: route('app.masters.email.templates.create'),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create email template"
            description="Define a reusable subject, message body, and default recipients."
        >
            <TemplateForm
                mode="create"
                initialValues={initialValues}
                statusOptions={statusOptions}
                providerOptions={providerOptions}
            />
        </AppLayout>
    );
}
