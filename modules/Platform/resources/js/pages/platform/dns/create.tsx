import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import DnsRecordForm from '../../../components/dns/dns-record-form';
import type { DomainDnsRecordFormValues, PlatformOption } from '../../../types/platform';

type DnsCreatePageProps = {
    domain: {
        id: number;
        name: string;
    };
    initialValues: DomainDnsRecordFormValues;
    typeOptions: PlatformOption[];
    ttlOptions: PlatformOption[];
};

export default function DnsCreate({ domain, initialValues, typeOptions, ttlOptions }: DnsCreatePageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
        { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
        { title: domain.name, href: route('platform.domains.show', domain.id) },
        { title: 'DNS records', href: route('platform.dns.index', { status: 'all', domain_id: domain.id }) },
        { title: 'Create', href: route('platform.dns.create', { domain_id: domain.id }) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Add DNS record for ${domain.name}`}
            description="Create a DNS entry with the correct host, TTL, and routing values for this domain."
        >
            <DnsRecordForm
                mode="create"
                domain={domain}
                initialValues={initialValues}
                typeOptions={typeOptions}
                ttlOptions={ttlOptions}
            />
        </AppLayout>
    );
}
