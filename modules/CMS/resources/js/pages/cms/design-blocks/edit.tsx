import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import DesignBlockForm from '../../../components/design-blocks/design-block-form';
import type { DesignBlockEditPageProps } from '../../../types/cms';

export default function DesignBlocksEdit({
    designBlock,
    ...props
}: DesignBlockEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Design Blocks', href: route('cms.designblock.index') },
        {
            title: designBlock.title,
            href: route('cms.designblock.edit', designBlock.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${designBlock.title}`}
            description="Update the design block content, code, and publishing settings."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.designblock.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Design Blocks
                    </Link>
                </Button>
            }
        >
            <DesignBlockForm mode="edit" designBlock={designBlock} {...props} />
        </AppLayout>
    );
}
