import AddressForm from '@/components/addresses/address-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AddressCreatePageProps } from '@/types/address';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Addresses', href: route('app.masters.addresses.index') },
    { title: 'New address', href: route('app.masters.addresses.create') },
];

export default function AddressesCreate({
    initialValues,
    typeOptions,
}: AddressCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create address"
            description="Add a new address to the system."
        >
            <AddressForm
                mode="create"
                initialValues={initialValues}
                typeOptions={typeOptions}
            />
        </AppLayout>
    );
}
