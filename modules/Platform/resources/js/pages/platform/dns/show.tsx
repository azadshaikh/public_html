import { Link } from '@inertiajs/react';
import { PencilIcon, PlusIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { DomainDnsRecordShowData } from '../../../types/platform';

type DnsShowPageProps = {
    domainDnsRecord: DomainDnsRecordShowData;
};

function MetricCard({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium text-foreground">{value || '—'}</p>
        </div>
    );
}

export default function DnsShow({ domainDnsRecord }: DnsShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
        { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
        ...(domainDnsRecord.domain_id && domainDnsRecord.domain_name
            ? [
                  { title: domainDnsRecord.domain_name, href: route('platform.domains.show', domainDnsRecord.domain_id) },
                  { title: 'DNS records', href: route('platform.dns.index', { status: 'all', domain_id: domainDnsRecord.domain_id }) },
              ]
            : [{ title: 'DNS records', href: route('platform.dns.index', { status: 'all' }) }]),
        { title: domainDnsRecord.name, href: route('platform.dns.show', domainDnsRecord.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={domainDnsRecord.name}
            description="Inspect the stored DNS target, routing details, and provider references for this record."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="outline" asChild>
                        <Link href={route('platform.dns.create', { domain_id: domainDnsRecord.domain_id })}>
                            <PlusIcon data-icon="inline-start" />
                            Add record
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('platform.dns.edit', domainDnsRecord.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit record
                        </Link>
                    </Button>
                </div>
            }
        >
            <Card>
                <CardHeader>
                    <CardTitle>DNS record details</CardTitle>
                    <CardDescription>Stored record metadata, routing targets, and provider references.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard label="Domain" value={domainDnsRecord.domain_name} />
                    <MetricCard label="Host" value={domainDnsRecord.name} />
                    <MetricCard label="Type" value={domainDnsRecord.type_label} />
                    <MetricCard label="TTL" value={domainDnsRecord.ttl} />
                    <MetricCard label="Value" value={domainDnsRecord.value} />
                    <MetricCard label="Priority" value={domainDnsRecord.priority} />
                    <MetricCard label="Weight" value={domainDnsRecord.weight} />
                    <MetricCard label="Port" value={domainDnsRecord.port} />
                    <MetricCard label="Disabled" value={domainDnsRecord.disabled ? 'Yes' : 'No'} />
                    <MetricCard label="Provider record ID" value={domainDnsRecord.record_id} />
                    <MetricCard label="Provider zone ID" value={domainDnsRecord.zone_id} />
                    <MetricCard label="Created" value={domainDnsRecord.created_at} />
                    <MetricCard label="Updated" value={domainDnsRecord.updated_at} />
                    <MetricCard label="Deleted" value={domainDnsRecord.deleted_at} />
                </CardContent>
            </Card>
        </AppLayout>
    );
}
