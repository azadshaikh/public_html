import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import PaymentForm from '../../../components/payments/payment-form';
import type { PaymentCreatePageProps } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Payments', href: route('app.billing.payments.index') },
    { title: 'Create', href: route('app.billing.payments.create') },
];

export default function PaymentsCreate(props: PaymentCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Payment"
            description="Create a new billing payment"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.payments.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <PaymentForm mode="create" {...props} />
        </AppLayout>
    );
}
