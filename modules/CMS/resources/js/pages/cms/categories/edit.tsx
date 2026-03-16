import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, ExternalLinkIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CategoryForm from '../../../components/categories/category-form';
import type { CategoryEditPageProps } from '../../../types/cms';

export default function CategoriesEdit({ category, ...props }: CategoryEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Categories', href: route('cms.categories.index') },
        { title: category.title, href: route('cms.categories.edit', category.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${category.title}`}
            description="Update the category content, publishing settings, and metadata."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    {category.permalink_url ? (
                        <Button variant="outline" asChild>
                            <a
                                href={category.permalink_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                View
                            </a>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <Link href={route('cms.categories.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to Categories
                        </Link>
                    </Button>
                </div>
            }
        >
            <CategoryForm mode="edit" category={category} {...props} />
        </AppLayout>
    );
}
