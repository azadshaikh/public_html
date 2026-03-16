import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, ExternalLinkIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TagForm from '../../../components/tags/tag-form';
import type { TagEditPageProps } from '../../../types/cms';

export default function TagsEdit({ tag, ...props }: TagEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Tags', href: route('cms.tags.index') },
        { title: tag.title, href: route('cms.tags.edit', tag.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${tag.title}`}
            description="Update the tag content, publishing settings, and metadata."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    {tag.permalink_url ? (
                        <Button variant="outline" asChild>
                            <a
                                href={tag.permalink_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                View
                            </a>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <Link href={route('cms.tags.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to Tags
                        </Link>
                    </Button>
                </div>
            }
        >
            <TagForm mode="edit" tag={tag} {...props} />
        </AppLayout>
    );
}
