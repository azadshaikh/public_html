import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import PaymentForm from '../../../components/payments/payment-form';
import type { PaymentEditPageProps } from '../../../types/billing';

export default function PaymentsEdit({ payment, ...props }: PaymentEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Payments', href: route('app.billing.payments.index') },
        { title: payment.name, href: route('app.billing.payments.show', payment.id) },
        { title: 'Edit', href: route('app.billing.payments.edit', payment.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${payment.name}`}
            description="Update payment details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.payments.show', payment.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <PaymentForm mode="edit" payment={payment} {...props} />
        </AppLayout>
    );
}
