import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import InvoiceForm from '../../../components/invoices/invoice-form';
import type { InvoiceEditPageProps } from '../../../types/billing';

export default function InvoicesEdit({ invoice, ...props }: InvoiceEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Invoices', href: route('app.billing.invoices.index') },
        { title: invoice.name, href: route('app.billing.invoices.show', invoice.id) },
        { title: 'Edit', href: route('app.billing.invoices.edit', invoice.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${invoice.name}`}
            description="Update invoice details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.invoices.show', invoice.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <InvoiceForm mode="edit" invoice={invoice} {...props} />
        </AppLayout>
    );
}
