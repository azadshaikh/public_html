import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    ExternalLinkIcon,
    PanelsTopLeftIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import PageForm from '../../../components/pages/page-form';
import type { PageEditPageProps } from '../../../types/cms';

export default function PagesEdit({ page, ...props }: PageEditPageProps) {
    const shortTitle =
        page.title.length > 40
            ? `${page.title.substring(0, 40)}...`
            : page.title;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Pages', href: route('cms.pages.index') },
        { title: shortTitle, href: route('cms.pages.edit', page.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${shortTitle}`}
            description="Update the page content, publishing settings, and metadata."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    {page.permalink_url ? (
                        <Button variant="outline" asChild>
                            <a
                                href={page.permalink_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                View
                            </a>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <a
                            href={route('cms.builder.edit', page.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <PanelsTopLeftIcon data-icon="inline-start" />
                            Edit in Builder
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('cms.pages.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to Pages
                        </Link>
                    </Button>
                </div>
            }
        >
            <PageForm mode="edit" page={page} {...props} />
        </AppLayout>
    );
}
