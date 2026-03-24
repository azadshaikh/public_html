import { Link } from '@inertiajs/react';
import { GlobeIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn, DatagridFilter } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { BreadcrumbItem } from '@/types';
import type { ScaffoldIndexPageProps } from '@/types/scaffold';

type DomainRow = {
    id: number;
    name: string | null;
    domain: string;
    status: string;
    status_label: string;
    dns_mode: 'managed' | 'external' | 'subdomain';
    show_url: string;
    created_at: string | null;
};

type AgencyDomainsIndexPageProps = ScaffoldIndexPageProps<DomainRow>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Domains', href: route('agency.domains.index') },
];

function dnsModeLabel(value: DomainRow['dns_mode']): string {
    switch (value) {
        case 'managed':
            return 'Managed DNS';
        case 'external':
            return 'External DNS';
        default:
            return 'Subdomain';
    }
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'secondary' {
    switch (status) {
        case 'active':
            return 'success';
        case 'waiting_for_dns':
        case 'provisioning':
            return 'warning';
        case 'failed':
        case 'expired':
            return 'danger';
        default:
            return 'secondary';
    }
}

export default function AgencyDomainsIndex({
    config,
    rows,
    filters,
    statistics,
}: AgencyDomainsIndexPageProps) {
    const { perPage, sorting } = buildScaffoldDatagridState(
        config,
        filters,
        statistics,
        {
            searchPlaceholder: 'Search domains...',
            perPageOptions: [10, 25, 50],
        },
    );

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search domains...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'dns_mode',
            value: typeof filters.dns_mode === 'string' ? filters.dns_mode : 'all',
            options: [
                { value: 'all', label: 'All DNS modes' },
                { value: 'managed', label: 'Managed DNS' },
                { value: 'external', label: 'External DNS' },
            ],
        },
        {
            type: 'select',
            name: 'status',
            value: typeof filters.status === 'string' ? filters.status : 'all',
            options: [
                { value: 'all', label: 'All statuses' },
                { value: 'active', label: 'Active' },
                { value: 'provisioning', label: 'Provisioning' },
                { value: 'waiting_for_dns', label: 'Waiting for DNS' },
                { value: 'failed', label: 'Failed' },
                { value: 'suspended', label: 'Suspended' },
                { value: 'expired', label: 'Expired' },
            ],
        },
    ];

    const columns: DatagridColumn<DomainRow>[] = [
        {
            key: 'domain',
            header: 'Domain',
            sortable: true,
            cardLabel: 'Domain',
            cell: (domain) => (
                <div className="flex min-w-0 flex-col gap-1">
                    <Link
                        href={domain.show_url}
                        className="font-medium text-foreground hover:text-primary"
                    >
                        {domain.domain}
                    </Link>
                    <span className="truncate text-xs text-muted-foreground">
                        {domain.name && domain.name !== domain.domain
                            ? domain.name
                            : 'Connected website'}
                    </span>
                </div>
            ),
        },
        {
            key: 'dns_mode',
            header: 'DNS Mode',
            cardLabel: 'DNS Mode',
            cell: (domain) => (
                <Badge variant="outline">{dnsModeLabel(domain.dns_mode)}</Badge>
            ),
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cardLabel: 'Status',
            cell: (domain) => (
                <Badge variant={statusVariant(domain.status)}>
                    {domain.status_label}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            header: 'Created',
            sortable: true,
            cardLabel: 'Created',
            cell: (domain) => formatDate(domain.created_at),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Domains"
            description="Inspect DNS mode and open per-domain configuration details."
        >
            <Datagrid
                action={route('agency.domains.index')}
                rows={rows}
                columns={columns}
                scaffoldColumns={config.columns}
                filters={gridFilters}
                getRowKey={(domain) => domain.id}
                sorting={sorting}
                perPage={perPage}
                view={{
                    value: filters.view === 'table' ? 'table' : 'cards',
                    storageKey: 'agency-domains-datagrid-view',
                }}
                renderCardHeader={(domain) => (
                    <div className="flex min-w-0 items-start justify-between gap-3">
                        <div className="min-w-0 space-y-1">
                            <Link
                                href={domain.show_url}
                                className="block truncate font-semibold text-foreground hover:text-primary"
                            >
                                {domain.domain}
                            </Link>
                            <p className="truncate text-sm text-muted-foreground">
                                {domain.name && domain.name !== domain.domain
                                    ? domain.name
                                    : 'Connected website'}
                            </p>
                        </div>
                        <Badge variant={statusVariant(domain.status)}>
                            {domain.status_label}
                        </Badge>
                    </div>
                )}
                renderCard={(domain) => (
                    <div className="flex flex-col gap-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <p className="text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    DNS Mode
                                </p>
                                <div>
                                    <Badge variant="outline">{dnsModeLabel(domain.dns_mode)}</Badge>
                                </div>
                            </div>
                            <div className="space-y-1">
                                <p className="text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Created
                                </p>
                                <p className="text-sm text-foreground">
                                    {formatDate(domain.created_at)}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center justify-end border-t border-border/60 pt-4">
                            <Link
                                href={domain.show_url}
                                className="font-medium text-primary hover:underline"
                            >
                                Open
                            </Link>
                        </div>
                    </div>
                )}
                cardGridClassName="grid-cols-1 xl:grid-cols-2"
                empty={{
                    icon: <GlobeIcon className="size-5" />,
                    title: 'No domains found',
                    description: 'Domains will appear here once websites are created.',
                }}
            />
        </AppLayout>
    );
}
