import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import DnsRecordForm from '../../../components/dns/dns-record-form';
import type { DomainDnsRecordFormValues, PlatformOption } from '../../../types/platform';

type DnsEditPageProps = {
    domain: {
        id: number;
        name: string;
    };
    domainDnsRecord: {
        id: number;
        name: string;
        type_label?: string | null;
    };
    initialValues: DomainDnsRecordFormValues;
    typeOptions: PlatformOption[];
    ttlOptions: PlatformOption[];
};

export default function DnsEdit({ domain, domainDnsRecord, initialValues, typeOptions, ttlOptions }: DnsEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
        { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
        { title: domain.name, href: route('platform.domains.show', domain.id) },
        { title: 'DNS records', href: route('platform.dns.index', { status: 'all', domain_id: domain.id }) },
        { title: domainDnsRecord.name, href: route('platform.dns.show', domainDnsRecord.id) },
        { title: 'Edit', href: route('platform.dns.edit', domainDnsRecord.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit DNS record ${domainDnsRecord.name}`}
            description="Update host routing, TTL, and provider references for this DNS record."
        >
            <DnsRecordForm
                mode="edit"
                domain={domain}
                record={domainDnsRecord}
                initialValues={initialValues}
                typeOptions={typeOptions}
                ttlOptions={ttlOptions}
            />
        </AppLayout>
    );
}
