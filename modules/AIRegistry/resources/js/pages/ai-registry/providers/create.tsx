import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import ProviderForm from '../../../components/providers/provider-form';
import type { AiProviderCreatePageProps } from '../../../types/ai-registry';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'AI Registry', href: route('ai-registry.providers.index', { status: 'all' }) },
    { title: 'Providers', href: route('ai-registry.providers.index', { status: 'all' }) },
    { title: 'Create', href: route('ai-registry.providers.create') },
];

export default function ProvidersCreate(props: AiProviderCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create AI provider"
            description="Register a new upstream provider and describe the capabilities it supports."
        >
            <ProviderForm
                mode="create"
                initialValues={props.initialValues}
                capabilityOptions={props.capabilityOptions}
            />
        </AppLayout>
    );
}
