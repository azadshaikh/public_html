import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import DesignBlockForm from '../../../components/design-blocks/design-block-form';
import type { DesignBlockCreatePageProps } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Design Blocks', href: route('cms.designblock.index') },
    { title: 'Create', href: route('cms.designblock.create') },
];

export default function DesignBlocksCreate(props: DesignBlockCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Design Block"
            description="Build a new reusable design component or section."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.designblock.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Design Blocks
                    </Link>
                </Button>
            }
        >
            <DesignBlockForm mode="create" {...props} />
        </AppLayout>
    );
}
