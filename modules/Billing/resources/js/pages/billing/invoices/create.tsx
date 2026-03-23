import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import InvoiceForm from '../../../components/invoices/invoice-form';
import type { InvoiceCreatePageProps } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Invoices', href: route('app.billing.invoices.index') },
    { title: 'Create', href: route('app.billing.invoices.create') },
];

export default function InvoicesCreate(props: InvoiceCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Invoice"
            description="Create a new billing invoice"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.invoices.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <InvoiceForm mode="create" {...props} />
        </AppLayout>
    );
}
