import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import CmsPageForm from '../../components/cms-page-form';
import type { CmsPageFormValues } from '../../components/cms-page-form';

type Option = { value: string; label: string };

type CmsEditPageProps = {
  module: {
    name: string;
    slug: string;
    version: string;
    description: string;
  };
  page: {
    id: number;
    title: string;
  };
  initialValues: CmsPageFormValues;
  options: {
    statusOptions: Option[];
  };
};

export default function CmsEdit({
  module,
  page,
  initialValues,
  options,
}: CmsEditPageProps) {
  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: module.name, href: '/cms' },
    { title: page.title, href: `/cms/${page.id}/edit` },
  ];

  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title={`Edit ${page.title}`}
      description={module.description}
    >
      <Head title={`Edit ${page.title}`} />
      <CmsPageForm
        mode="edit"
        module={module}
        page={page}
        initialValues={initialValues}
        options={options}
      />
    </AppLayout>
  );
}
