import { Link, usePage } from '@inertiajs/react';
import { HardDriveIcon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import {
    buildScaffoldActionHandlers,
    buildScaffoldDatagridState,
    buildScaffoldEmptyState,
} from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { PlatformIndexPageProps, ServerListItem } from '../../../types/platform';

function serverStatusVariant(status: string | null, isTrashed?: boolean): 'success' | 'warning' | 'danger' | 'secondary' {
    if (isTrashed) {
        return 'danger';
    }

    switch ((status ?? '').toLowerCase()) {
        case 'active':
        case 'ready':
            return 'success';
        case 'maintenance':
        case 'provisioning':
            return 'warning';
        case 'failed':
        case 'deleted':
        case 'trash':
            return 'danger';
        default:
            return 'secondary';
    }
}

function serverTypeVariant(type: string | null): 'warning' | 'secondary' {
    return (type ?? '').toLowerCase() === 'localhost' ? 'warning' : 'secondary';
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.servers.index', { status: 'all' }),
    },
    {
        title: 'Servers',
        href: route('platform.servers.index', { status: 'all' }),
    },
];

export default function ServersIndex({
    config,
    rows,
    filters,
    statistics,
    empty_state_config,
}: PlatformIndexPageProps<ServerListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddServers = page.props.auth.abilities.addServers;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search servers...',
        perPageOptions: [10, 25, 50, 100],
    });
    const { rowActions, bulkActions } = buildScaffoldActionHandlers(config, {
        bulkActionUrl: route('platform.servers.bulk-action'),
        currentStatus,
    });

    const columns: DatagridColumn<ServerListItem>[] = [
        {
            key: 'uid',
            header: 'UID',
            sortable: true,
            cell: (server) => (
                <Badge variant="secondary" className="font-mono text-[0.7rem]">
                    {server.uid ?? '—'}
                </Badge>
            ),
        },
        {
            key: 'name',
            header: 'Server',
            sortable: true,
            cell: (server) => (
                <div className="min-w-0">
                    <Link
                        href={route('platform.servers.show', server.id)}
                        className="block truncate font-medium text-foreground hover:text-primary"
                    >
                        {server.name}
                    </Link>
                </div>
            ),
        },
        {
            key: 'ip',
            header: 'IP',
            sortable: true,
            cell: (server) => (
                <span className="font-medium text-foreground">{server.ip}</span>
            ),
        },
        {
            key: 'type_label',
            header: 'Type',
            cell: (server) => (
                <Badge variant={serverTypeVariant(server.type)}>{server.type_label}</Badge>
            ),
        },
        {
            key: 'provider_name',
            header: 'Provider',
        },
        {
            key: 'domain_usage_current',
            header: 'Domains',
            sortable: true,
            sortKey: 'current_domains',
            cell: (server) => (
                <div className="flex min-w-[5.5rem] flex-col gap-1">
                    <span className="text-xs font-medium text-muted-foreground">
                        {server.domain_usage_current}
                        {server.domain_usage_max ? `/${server.domain_usage_max}` : ''}
                    </span>
                    {server.domain_usage_max ? (
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-muted-foreground/35"
                                style={{ width: `${Math.min(server.domain_usage_percent ?? 0, 100)}%` }}
                            />
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cell: (server) => (
                <Badge variant={serverStatusVariant(server.status, server.is_trashed)}>
                    {server.status_label}
                </Badge>
            ),
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
            title="Servers"
            description="Manage your server infrastructure and hosting"
            headerActions={
                canAddServers ? (
                    <Button asChild>
                        <Link href={route('platform.servers.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Server
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.servers.index', {
                    status: currentStatus,
                })}
                rows={rows}
                columns={columns}
                scaffoldColumns={config.columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(server) => server.id}
                rowActions={rowActions}
                bulkActions={bulkActions}
                empty={buildScaffoldEmptyState(empty_state_config, {
                    icon: <HardDriveIcon className="size-5" />,
                    fallbackTitle: 'No servers found',
                    fallbackDescription:
                        'Add an existing server or provision a fresh VPS to get started.',
                })}
                sorting={sorting}
                perPage={perPage}
                title="Servers"
                description="Manage your server infrastructure and hosting"
            />
        </AppLayout>
    );
}
