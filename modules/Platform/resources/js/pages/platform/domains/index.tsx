import { Link, usePage } from '@inertiajs/react';
import { GlobeIcon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn, DatagridTab } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { buildBulkActions, mapFilters, mapRowActions, mapStatusTab } from '../../../lib/helpers';
import type { DomainListItem, PlatformIndexPageProps } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
    { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
];

export default function DomainsIndex({ config, rows, filters, statistics }: PlatformIndexPageProps<DomainListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddDomains = page.props.auth.abilities.addDomains;

    const gridFilters = mapFilters(config.filters, filters, 'Search domains...');
    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) =>
        mapStatusTab(tab, statistics, String(filters.status ?? 'all')),
    );

    const columns: DatagridColumn<DomainListItem>[] = [
        {
            key: 'name',
            header: 'Domain',
            sortable: true,
            cell: (domain) => (
                <div className="flex flex-col gap-1">
                    <Link href={route('platform.domains.show', domain.id)} className="font-medium text-foreground hover:text-primary">
                        {domain.name}
                    </Link>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {domain.agency_name ? <span>{domain.agency_name}</span> : null}
                        {domain.registrar_name ? <span>{domain.registrar_name}</span> : null}
                    </div>
                </div>
            ),
        },
        {
            key: 'type_label',
            header: 'Type',
            sortable: true,
            sortKey: 'type',
            cell: (domain) => <Badge variant="secondary">{domain.type_label}</Badge>,
        },
        {
            key: 'registrar_name',
            header: 'Registrar',
        },
        {
            key: 'expiry_date',
            header: 'Expires',
            sortable: true,
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cell: (domain) => <Badge variant={domain.is_trashed ? 'destructive' : 'secondary'}>{domain.status_label}</Badge>,
        },
        {
            key: 'created_at',
            header: 'Created',
            sortable: true,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Domains"
            description="Manage registrar ownership, WHOIS milestones, and DNS metadata across platform domains."
            headerActions={
                canAddDomains ? (
                    <Button asChild>
                        <Link href={route('platform.domains.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add domain
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.domains.index', { status: filters.status ?? 'all' })}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(domain) => domain.id}
                rowActions={(domain) => mapRowActions(domain.actions)}
                bulkActions={buildBulkActions(config.actions, config.settings.routePrefix, String(filters.status ?? 'all'))}
                empty={{
                    icon: <GlobeIcon className="size-5" />,
                    title: 'No domains found',
                    description: 'Add a domain to start tracking registrar details, SSL coverage, and DNS records.',
                }}
                sorting={{
                    sort: String(filters.sort ?? config.settings.defaultSort ?? 'created_at'),
                    direction:
                        String(filters.direction ?? config.settings.defaultDirection ?? 'desc') === 'asc'
                            ? 'asc'
                            : 'desc',
                }}
                perPage={{
                    value: Number(filters.per_page ?? config.settings.perPage ?? rows.per_page),
                    options: [15, 25, 50, 100],
                    paramName: 'per_page',
                }}
                title="Registered domains"
                description="Review expiration windows, registrar coverage, and agency ownership for every domain."
            />
        </AppLayout>
    );
}
