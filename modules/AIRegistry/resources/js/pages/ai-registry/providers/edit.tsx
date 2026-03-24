import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import ProviderForm from '../../../components/providers/provider-form';
import type { AiProviderEditPageProps } from '../../../types/ai-registry';

export default function ProvidersEdit(props: AiProviderEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'AI Registry', href: route('ai-registry.providers.index', { status: 'all' }) },
        { title: 'Providers', href: route('ai-registry.providers.index', { status: 'all' }) },
        { title: props.aiProvider.name, href: route('ai-registry.providers.edit', props.aiProvider.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.aiProvider.name}`}
            description="Refine provider metadata, references, and capability flags used by the AI registry."
        >
            <ProviderForm
                mode="edit"
                provider={props.aiProvider}
                initialValues={props.initialValues}
                capabilityOptions={props.capabilityOptions}
            />
        </AppLayout>
    );
}
