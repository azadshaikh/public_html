import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import PromptTemplateForm from '../../components/prompt-template-form';
import type { PromptTemplateFormValues } from '../../components/prompt-template-form';

type Option = { value: string; label: string };

type ChatBotCreatePageProps = {
  module: {
    name: string;
    slug: string;
    version: string;
    description: string;
  };
  prompt: null;
  initialValues: PromptTemplateFormValues;
  options: {
    statusOptions: Option[];
    toneOptions: Option[];
  };
};

export default function ChatBotCreate({
  module,
  prompt,
  initialValues,
  options,
}: ChatBotCreatePageProps) {
  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: module.name, href: '/chatbot' },
    { title: 'Create prompt', href: '/chatbot/create' },
  ];

  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title={`Create ${module.name} prompt`}
      description={module.description}
    >
      <Head title={`Create ${module.name} prompt`} />
      <PromptTemplateForm
        mode="create"
        module={module}
        prompt={prompt}
        initialValues={initialValues}
        options={options}
      />
    </AppLayout>
  );
}
