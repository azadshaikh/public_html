import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CustomerContactForm from '../../../components/customers/customer-contact-form';
import type { CustomerContactCreatePageProps } from '../../../types/customers';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Customers', href: route('app.customers.index') },
    { title: 'Contacts', href: route('app.customers.contacts.index') },
    { title: 'Create', href: route('app.customers.contacts.create') },
];

export default function CustomerContactsCreate(
    props: CustomerContactCreatePageProps,
) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Contact"
            description="Add a new customer contact"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.customers.contacts.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <CustomerContactForm mode="create" {...props} />
        </AppLayout>
    );
}
