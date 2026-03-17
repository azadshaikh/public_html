import { Link, usePage } from '@inertiajs/react';
import { GlobeIcon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn, DatagridTab } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { buildBulkActions, mapFilters, mapRowActions, mapStatusTab } from '../../../lib/helpers';
import type { PlatformIndexPageProps, WebsiteListItem } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Platform', href: route('platform.websites.index', { status: 'all' }) },
    { title: 'Websites', href: route('platform.websites.index', { status: 'all' }) },
];

export default function WebsitesIndex({ config, rows, filters, statistics }: PlatformIndexPageProps<WebsiteListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddWebsites = page.props.auth.abilities.addWebsites;

    const gridFilters = mapFilters(config.filters, filters, 'Search websites...');
    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) =>
        mapStatusTab(tab, statistics, String(filters.status ?? 'all')),
    );

    const columns: DatagridColumn<WebsiteListItem>[] = [
        {
            key: 'name',
            header: 'Website',
            sortable: true,
            cell: (website) => (
                <div className="flex flex-col gap-1">
                    <Link href={route('platform.websites.show', website.id)} className="font-medium text-foreground hover:text-primary">
                        {website.name}
                    </Link>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {website.uid ? <span>{website.uid}</span> : null}
                        <span>{website.domain}</span>
                    </div>
                </div>
            ),
        },
        {
            key: 'customer_name',
            header: 'Customer',
        },
        {
            key: 'agency_name',
            header: 'Agency',
            cell: (website) => <Badge variant="secondary">{website.agency_name}</Badge>,
        },
        {
            key: 'server_name',
            header: 'Server',
            cell: (website) => <Badge variant="outline">{website.server_name}</Badge>,
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cell: (website) => <Badge variant={website.is_trashed ? 'destructive' : 'secondary'}>{website.status_label}</Badge>,
        },
        {
            key: 'dns_mode_label',
            header: 'DNS',
        },
        {
            key: 'cdn_status_label',
            header: 'CDN',
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
            title="Websites"
            description="Track website provisioning, customer context, and server placement."
            headerActions={
                canAddWebsites ? (
                    <Button asChild>
                        <Link href={route('platform.websites.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add website
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.websites.index', { status: filters.status ?? 'all' })}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(website) => website.id}
                rowActions={(website) => mapRowActions(website.actions)}
                bulkActions={buildBulkActions(config.actions, config.settings.routePrefix, String(filters.status ?? 'all'))}
                empty={{
                    icon: <GlobeIcon className="size-5" />,
                    title: 'No websites found',
                    description: 'Create the first website to start automated provisioning and lifecycle tracking.',
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
                title="Provisioned websites"
                description="Monitor deployment status, DNS routing, and customer associations for every site."
            />
        </AppLayout>
    );
}
