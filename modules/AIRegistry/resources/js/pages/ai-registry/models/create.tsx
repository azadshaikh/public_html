import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import ModelForm from '../../../components/models/model-form';
import type { AiModelCreatePageProps } from '../../../types/ai-registry';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'AI Registry', href: route('ai-registry.models.index', { status: 'all' }) },
    { title: 'Models', href: route('ai-registry.models.index', { status: 'all' }) },
    { title: 'Create', href: route('ai-registry.models.create') },
];

export default function ModelsCreate(props: AiModelCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create AI model"
            description="Register a model under an active provider with pricing, modality, and capability metadata."
        >
            <ModelForm
                mode="create"
                initialValues={props.initialValues}
                providerOptions={props.providerOptions}
                capabilityOptions={props.capabilityOptions}
                categoryOptions={props.categoryOptions}
                inputModalityOptions={props.inputModalityOptions}
                outputModalityOptions={props.outputModalityOptions}
            />
        </AppLayout>
    );
}
