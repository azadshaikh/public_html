import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import PageForm from '../../../components/pages/page-form';
import type { PageCreatePageProps } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Pages', href: route('cms.pages.index') },
    { title: 'Create', href: route('cms.pages.create') },
];

export default function PagesCreate(props: PageCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Page"
            description="Add a new page and configure its publishing settings."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.pages.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Pages
                    </Link>
                </Button>
            }
        >
            <PageForm mode="create" {...props} />
        </AppLayout>
    );
}
