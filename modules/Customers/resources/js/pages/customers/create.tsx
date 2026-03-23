import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CustomerForm from '../../components/customers/customer-form';
import type { CustomerCreatePageProps } from '../../types/customers';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Customers', href: route('app.customers.index') },
    { title: 'Create', href: route('app.customers.create') },
];

export default function CustomersCreate(props: CustomerCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Customer"
            description="Add a new customer"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.customers.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <CustomerForm mode="create" {...props} />
        </AppLayout>
    );
}
