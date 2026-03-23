import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import RefundForm from '../../../components/refunds/refund-form';
import type { RefundEditPageProps } from '../../../types/billing';

export default function RefundsEdit({ refund, ...props }: RefundEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Refunds', href: route('app.billing.refunds.index') },
        { title: refund.name, href: route('app.billing.refunds.show', refund.id) },
        { title: 'Edit', href: route('app.billing.refunds.edit', refund.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${refund.name}`}
            description="Update refund details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.refunds.show', refund.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <RefundForm mode="edit" refund={refund} {...props} />
        </AppLayout>
    );
}
