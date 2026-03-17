import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import ProviderForm from '../../../components/providers/provider-form';
import type { PlatformOption, ProviderFormValues } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Platform', href: route('platform.providers.index', { status: 'all' }) },
    { title: 'Providers', href: route('platform.providers.index', { status: 'all' }) },
    { title: 'Create', href: route('platform.providers.create') },
];

type ProvidersCreatePageProps = {
    initialValues: ProviderFormValues;
    typeOptions: PlatformOption[];
    vendorOptions: PlatformOption[];
    statusOptions: PlatformOption[];
};

export default function ProvidersCreate(props: ProvidersCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create provider"
            description="Connect a new upstream provider account for DNS, CDN, registrar, or infrastructure services."
        >
            <ProviderForm
                mode="create"
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                vendorOptions={props.vendorOptions}
                statusOptions={props.statusOptions}
            />
        </AppLayout>
    );
}
