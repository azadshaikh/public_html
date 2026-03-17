import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import DomainForm from '../../../components/domains/domain-form';
import type { DomainFormValues, PlatformOption } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.domains.index', { status: 'all' }),
    },
    {
        title: 'Domains',
        href: route('platform.domains.index', { status: 'all' }),
    },
    { title: 'Create', href: route('platform.domains.create') },
];

type DomainsCreatePageProps = {
    initialValues: DomainFormValues;
    typeOptions: PlatformOption[];
    agencyOptions: PlatformOption[];
    registrarOptions: PlatformOption[];
    statusOptions: PlatformOption[];
};

export default function DomainsCreate(props: DomainsCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create domain"
            description="Register a platform domain record with agency, registrar, and DNS context."
        >
            <DomainForm
                mode="create"
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                agencyOptions={props.agencyOptions}
                registrarOptions={props.registrarOptions}
                statusOptions={props.statusOptions}
            />
        </AppLayout>
    );
}
