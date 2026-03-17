import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import DomainForm from '../../../components/domains/domain-form';
import type { DomainFormValues, PlatformOption } from '../../../types/platform';

type DomainsEditPageProps = {
    domain: {
        id: number;
        name: string;
    };
    initialValues: DomainFormValues;
    typeOptions: PlatformOption[];
    agencyOptions: PlatformOption[];
    registrarOptions: PlatformOption[];
    statusOptions: PlatformOption[];
};

export default function DomainsEdit(props: DomainsEditPageProps) {
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
        {
            title: props.domain.name,
            href: route('platform.domains.show', props.domain.id),
        },
        {
            title: 'Edit',
            href: route('platform.domains.edit', props.domain.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.domain.name}`}
            description="Update registrar mapping, WHOIS dates, and DNS metadata for this domain."
        >
            <DomainForm
                mode="edit"
                domain={props.domain}
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                agencyOptions={props.agencyOptions}
                registrarOptions={props.registrarOptions}
                statusOptions={props.statusOptions}
            />
        </AppLayout>
    );
}
