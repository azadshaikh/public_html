import ProviderForm from '@/components/email-providers/provider-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmailProviderEditPageProps } from '@/types/email';

export default function EmailProvidersEdit({
    emailProvider,
    initialValues,
    statusOptions,
    encryptionOptions,
}: EmailProviderEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Email Providers', href: route('app.masters.email.providers.index') },
        {
            title: emailProvider.name,
            href: route('app.masters.email.providers.edit', emailProvider.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${emailProvider.name}`}
            description="Update sender identity, SMTP credentials, and provider availability."
        >
            <ProviderForm
                mode="edit"
                emailProvider={emailProvider}
                initialValues={initialValues}
                statusOptions={statusOptions}
                encryptionOptions={encryptionOptions}
            />
        </AppLayout>
    );
}
