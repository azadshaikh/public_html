import TemplateForm from '@/components/email-templates/template-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmailTemplateEditPageProps } from '@/types/email';

export default function EmailTemplatesEdit({
    emailTemplate,
    initialValues,
    statusOptions,
    providerOptions,
}: EmailTemplateEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Email Templates', href: route('app.masters.email.templates.index') },
        {
            title: emailTemplate.name,
            href: route('app.masters.email.templates.edit', emailTemplate.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${emailTemplate.name}`}
            description="Update the message copy, provider mapping, and delivery status."
        >
            <TemplateForm
                mode="edit"
                emailTemplate={emailTemplate}
                initialValues={initialValues}
                statusOptions={statusOptions}
                providerOptions={providerOptions}
            />
        </AppLayout>
    );
}
