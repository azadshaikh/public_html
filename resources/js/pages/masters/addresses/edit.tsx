import AddressForm from '@/components/addresses/address-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AddressEditPageProps } from '@/types/address';

export default function AddressesEdit({
    address,
    initialValues,
    typeOptions,
}: AddressEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Addresses', href: route('app.masters.addresses.index') },
        {
            title: address.full_name ?? `Address #${address.id}`,
            href: route('app.masters.addresses.edit', address.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Edit address"
            description={`Update the address details for ${address.full_name ?? `#${address.id}`}.`}
        >
            <AddressForm
                mode="edit"
                address={address}
                initialValues={initialValues}
                typeOptions={typeOptions}
            />
        </AppLayout>
    );
}
