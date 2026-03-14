import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CmsPageForm from '../../components/cms-page-form';
import type { CmsPageFormValues } from '../../components/cms-page-form';

type Option = { value: string; label: string };

type CmsCreatePageProps = {
    module: {
        name: string;
        slug: string;
        version: string;
        description: string;
    };
    page: null;
    initialValues: CmsPageFormValues;
    options: {
        statusOptions: Option[];
    };
};

export default function CmsCreate({
    module,
    page,
    initialValues,
    options,
}: CmsCreatePageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: module.name, href: '/cms' },
        { title: 'Create page', href: '/cms/create' },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Create ${module.name} page`}
            description={module.description}
        >
            <CmsPageForm
                mode="create"
                module={module}
                page={page}
                initialValues={initialValues}
                options={options}
            />
        </AppLayout>
    );
}
