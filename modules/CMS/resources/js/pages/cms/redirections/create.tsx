import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import RedirectionForm from '../../../components/redirections/redirection-form';
import type { RedirectionCreatePageProps } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Redirections', href: route('cms.redirections.index') },
    { title: 'Create', href: route('cms.redirections.create') },
];

export default function RedirectionsCreate(props: RedirectionCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Redirection"
            description="Add a redirect rule for legacy URLs, campaign links, and content migrations."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.redirections.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Redirections
                    </Link>
                </Button>
            }
        >
            <RedirectionForm mode="create" {...props} />
        </AppLayout>
    );
}
