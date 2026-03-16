import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CategoryForm from '../../../components/categories/category-form';
import type { CategoryCreatePageProps } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Categories', href: route('cms.categories.index') },
    { title: 'Create', href: route('cms.categories.create') },
];

export default function CategoriesCreate(props: CategoryCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Category"
            description="Add a new category and configure its publishing settings."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.categories.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Categories
                    </Link>
                </Button>
            }
        >
            <CategoryForm mode="create" {...props} />
        </AppLayout>
    );
}
