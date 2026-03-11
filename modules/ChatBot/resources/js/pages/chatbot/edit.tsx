import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import PromptTemplateForm from '../../components/prompt-template-form';
import type { PromptTemplateFormValues } from '../../components/prompt-template-form';

type Option = { value: string; label: string };

type ChatBotEditPageProps = {
    module: {
        name: string;
        slug: string;
        version: string;
        description: string;
    };
    prompt: {
        id: number;
        name: string;
    };
    initialValues: PromptTemplateFormValues;
    options: {
        statusOptions: Option[];
        toneOptions: Option[];
    };
};

export default function ChatBotEdit({
    module,
    prompt,
    initialValues,
    options,
}: ChatBotEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard() },
        { title: module.name, href: '/chatbot' },
        { title: prompt.name, href: `/chatbot/${prompt.id}/edit` },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${prompt.name}`}
            description={module.description}
        >
            <Head title={`Edit ${prompt.name}`} />
            <PromptTemplateForm
                mode="edit"
                module={module}
                prompt={prompt}
                initialValues={initialValues}
                options={options}
            />
        </AppLayout>
    );
}
