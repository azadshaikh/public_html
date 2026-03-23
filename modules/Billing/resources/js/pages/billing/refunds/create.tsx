import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import RefundForm from '../../../components/refunds/refund-form';
import type { RefundCreatePageProps } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Refunds', href: route('app.billing.refunds.index') },
    { title: 'Create', href: route('app.billing.refunds.create') },
];

export default function RefundsCreate(props: RefundCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Refund"
            description="Create a new billing refund"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.refunds.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <RefundForm mode="create" {...props} />
        </AppLayout>
    );
}
