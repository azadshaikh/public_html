import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CustomerContactForm from '../../../components/customers/customer-contact-form';
import type { CustomerContactEditPageProps } from '../../../types/customers';

export default function CustomerContactsEdit({
    contact,
    ...props
}: CustomerContactEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Customers', href: route('app.customers.index') },
        { title: 'Contacts', href: route('app.customers.contacts.index') },
        {
            title: contact.name,
            href: route('app.customers.contacts.show', contact.id),
        },
        {
            title: 'Edit',
            href: route('app.customers.contacts.edit', contact.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${contact.name}`}
            description="Update contact details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link
                        href={route(
                            'app.customers.contacts.show',
                            contact.id,
                        )}
                    >
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <CustomerContactForm mode="edit" contact={contact} {...props} />
        </AppLayout>
    );
}
