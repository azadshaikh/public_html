import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import RedirectionForm from '../../../components/redirections/redirection-form';
import type { RedirectionEditPageProps } from '../../../types/cms';

export default function RedirectionsEdit({
    redirection,
    ...props
}: RedirectionEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Redirections', href: route('cms.redirections.index') },
        {
            title: redirection.source_url || 'Redirection',
            href: route('cms.redirections.edit', redirection.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit Redirection: ${redirection.source_url}`}
            description="Update the redirect rule, publishing window, and team notes."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.redirections.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Redirections
                    </Link>
                </Button>
            }
        >
            <RedirectionForm mode="edit" redirection={redirection} {...props} />
        </AppLayout>
    );
}
