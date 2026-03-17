import { Link, usePage } from '@inertiajs/react';
import { GlobeIcon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldBulkActions, buildScaffoldDatagridState, mapScaffoldRowActions } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { DomainDnsRecordListItem, PlatformIndexPageProps } from '../../../types/platform';

type DomainContext = {
    id: number;
    name: string;
} | null;

type DnsIndexPageProps = PlatformIndexPageProps<DomainDnsRecordListItem> & {
    domain: DomainContext;
};

export default function DnsIndex({ config, rows, filters, statistics, domain }: DnsIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddRecords = page.props.auth.abilities.addDomainDnsRecords;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
        ...(domain
            ? [
                  { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
                  { title: domain.name, href: route('platform.domains.show', domain.id) },
              ]
            : []),
        { title: 'DNS records', href: route('platform.dns.index', { status: 'all', ...(domain ? { domain_id: domain.id } : {}) }) },
    ];

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search DNS records...',
        perPageOptions: [15, 25, 50, 100],
    });

    const indexRouteParams = {
        status: currentStatus,
        ...(domain ? { domain_id: domain.id } : {}),
    };

    const bulkActionRouteParams = domain ? { domain_id: domain.id } : {};

    const columns: DatagridColumn<DomainDnsRecordListItem>[] = [
        {
            key: 'name',
            header: 'Host',
            sortable: true,
            cell: (record) => (
                <div className="flex flex-col gap-1">
                    <Link href={route('platform.dns.show', record.id)} className="font-medium text-foreground hover:text-primary">
                        {record.name}
                    </Link>
                    {record.domain_name ? <span className="text-xs text-muted-foreground">{record.domain_name}</span> : null}
                </div>
            ),
        },
        {
            key: 'type_label',
            header: 'Type',
            sortable: true,
            sortKey: 'type',
            cell: (record) => <Badge variant="secondary">{record.type_label}</Badge>,
        },
        {
            key: 'value',
            header: 'Value',
        },
        {
            key: 'ttl',
            header: 'TTL',
            sortable: true,
        },
        {
            key: 'updated_at',
            header: 'Updated',
            sortable: true,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={domain ? `${domain.name} DNS records` : 'DNS records'}
            description={domain ? `Manage DNS records published for ${domain.name}.` : 'Review DNS records across platform-managed domains.'}
            headerActions={
                canAddRecords ? (
                    <Button asChild>
                        <Link href={route('platform.dns.create', domain ? { domain_id: domain.id } : {})}>
                            <PlusIcon data-icon="inline-start" />
                            Add DNS record
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.dns.index', indexRouteParams)}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(record) => record.id}
                rowActions={(record) => mapScaffoldRowActions(record.actions)}
                bulkActions={buildScaffoldBulkActions(config.actions, {
                    bulkActionUrl: route('platform.dns.bulk-action', bulkActionRouteParams),
                    currentStatus,
                })}
                empty={{
                    icon: <GlobeIcon className="size-5" />,
                    title: 'No DNS records found',
                    description: 'Create a record to start managing hostnames, routing targets, and TTL policy.',
                }}
                sorting={sorting}
                perPage={perPage}
                title="DNS records"
                description="Inspect host records, record type coverage, and upstream routing values."
            />
        </AppLayout>
    );
}
