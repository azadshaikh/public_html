import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import ProviderForm from '../../../components/providers/provider-form';
import type {
    PlatformOption,
    ProviderFormValues,
} from '../../../types/platform';

type ProvidersEditPageProps = {
    provider: {
        id: number;
        name: string;
    };
    initialValues: ProviderFormValues;
    typeOptions: PlatformOption[];
    vendorOptions: PlatformOption[];
    statusOptions: PlatformOption[];
};

export default function ProvidersEdit(props: ProvidersEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Platform',
            href: route('platform.providers.index', { status: 'all' }),
        },
        {
            title: 'Providers',
            href: route('platform.providers.index', { status: 'all' }),
        },
        {
            title: props.provider.name,
            href: route('platform.providers.show', props.provider.id),
        },
        {
            title: 'Edit',
            href: route('platform.providers.edit', props.provider.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.provider.name}`}
            description="Update vendor credentials, account routing, and lifecycle settings for this provider."
        >
            <ProviderForm
                mode="edit"
                provider={props.provider}
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                vendorOptions={props.vendorOptions}
                statusOptions={props.statusOptions}
            />
        </AppLayout>
    );
}
