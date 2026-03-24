import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import ModelForm from '../../../components/models/model-form';
import type { AiModelEditPageProps } from '../../../types/ai-registry';

export default function ModelsEdit(props: AiModelEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'AI Registry', href: route('ai-registry.models.index', { status: 'all' }) },
        { title: 'Models', href: route('ai-registry.models.index', { status: 'all' }) },
        { title: props.aiModel.name, href: route('ai-registry.models.edit', props.aiModel.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.aiModel.name}`}
            description="Update provider assignment, pricing, modalities, and operational metadata for this model."
        >
            <ModelForm
                mode="edit"
                model={props.aiModel}
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
