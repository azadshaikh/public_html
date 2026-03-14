import ProviderForm from '@/components/email-providers/provider-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmailProviderCreatePageProps } from '@/types/email';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Email Providers', href: route('app.masters.email.providers.index') },
    { title: 'New provider', href: route('app.masters.email.providers.create') },
];

export default function EmailProvidersCreate({
    initialValues,
    statusOptions,
    encryptionOptions,
}: EmailProviderCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create email provider"
            description="Add an SMTP connection your templates can deliver through."
        >
            <ProviderForm
                mode="create"
                initialValues={initialValues}
                statusOptions={statusOptions}
                encryptionOptions={encryptionOptions}
            />
        </AppLayout>
    );
}
