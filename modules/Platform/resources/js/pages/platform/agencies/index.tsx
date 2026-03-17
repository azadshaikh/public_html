import { Link, usePage } from '@inertiajs/react';
import { Building2Icon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import {
    buildBulkActions,
    buildDatagridState,
    mapRowActions,
} from '../../../lib/helpers';
import type {
    AgencyListItem,
    PlatformIndexPageProps,
} from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.agencies.index', { status: 'all' }),
    },
    {
        title: 'Agencies',
        href: route('platform.agencies.index', { status: 'all' }),
    },
];

export default function AgenciesIndex({
    config,
    rows,
    filters,
    statistics,
}: PlatformIndexPageProps<AgencyListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddAgencies = page.props.auth.abilities.addAgencies;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildDatagridState(config, filters, statistics, 'Search agencies...');

    const columns: DatagridColumn<AgencyListItem>[] = [
        {
            key: 'name',
            header: 'Agency',
            sortable: true,
            cell: (agency) => (
                <div className="flex flex-col gap-1">
                    <Link
                        href={route('platform.agencies.show', agency.id)}
                        className="font-medium text-foreground hover:text-primary"
                    >
                        {agency.name}
                    </Link>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {agency.uid ? <span>{agency.uid}</span> : null}
                        {agency.email ? <span>{agency.email}</span> : null}
                    </div>
                </div>
            ),
        },
        {
            key: 'owner_name',
            header: 'Owner',
            sortable: true,
            sortKey: 'owner_id',
        },
        {
            key: 'type_label',
            header: 'Type',
            cell: (agency) => (
                <Badge variant="secondary">{agency.type_label}</Badge>
            ),
        },
        {
            key: 'plan_label',
            header: 'Plan',
            cell: (agency) => (
                <Badge variant="outline">{agency.plan_label}</Badge>
            ),
        },
        {
            key: 'websites_count',
            header: 'Websites',
            sortable: true,
            cellClassName: 'w-28',
            headerClassName: 'w-28',
            cell: (agency) => (
                <span className="font-medium">{agency.websites_count}</span>
            ),
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cell: (agency) => (
                <Badge
                    variant={agency.is_trashed ? 'destructive' : 'secondary'}
                >
                    {agency.status_label}
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
            title="Agencies"
            description="Manage agency tenants, ownership, branding, and routing defaults."
            headerActions={
                canAddAgencies ? (
                    <Button asChild>
                        <Link href={route('platform.agencies.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add agency
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.agencies.index', {
                    status: currentStatus,
                })}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(agency) => agency.id}
                rowActions={(agency) => mapRowActions(agency.actions)}
                bulkActions={buildBulkActions(
                    config.actions,
                    config.settings.routePrefix,
                    currentStatus,
                )}
                empty={{
                    icon: <Building2Icon className="size-5" />,
                    title: 'No agencies found',
                    description:
                        'Create the first agency to start organizing websites and servers.',
                }}
                sorting={sorting}
                perPage={perPage}
                title="Agency directory"
                description="Track plan tiers, ownership, and agency-managed websites in one place."
            />
        </AppLayout>
    );
}
