import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CustomerForm from '../../components/customers/customer-form';
import type { CustomerEditPageProps } from '../../types/customers';

export default function CustomersEdit({
    customer,
    ...props
}: CustomerEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Customers', href: route('app.customers.index') },
        {
            title: customer.name,
            href: route('app.customers.show', customer.id),
        },
        {
            title: 'Edit',
            href: route('app.customers.edit', customer.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${customer.name}`}
            description="Update customer details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.customers.show', customer.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <CustomerForm mode="edit" customer={customer} {...props} />
        </AppLayout>
    );
}
