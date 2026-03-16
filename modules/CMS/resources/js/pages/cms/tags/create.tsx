import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TagForm from '../../../components/tags/tag-form';
import type { TagCreatePageProps } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Tags', href: route('cms.tags.index') },
    { title: 'Create', href: route('cms.tags.create') },
];

export default function TagsCreate(props: TagCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Tag"
            description="Add a new tag and configure its publishing settings."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.tags.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Tags
                    </Link>
                </Button>
            }
        >
            <TagForm mode="create" {...props} />
        </AppLayout>
    );
}
