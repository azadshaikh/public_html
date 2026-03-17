import { Link, usePage } from '@inertiajs/react';
import { GlobeIcon, PlusIcon } from 'lucide-react';
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
    PlatformIndexPageProps,
    WebsiteListItem,
} from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.websites.index', { status: 'all' }),
    },
    {
        title: 'Websites',
        href: route('platform.websites.index', { status: 'all' }),
    },
];

export default function WebsitesIndex({
    config,
    rows,
    filters,
    statistics,
}: PlatformIndexPageProps<WebsiteListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddWebsites = page.props.auth.abilities.addWebsites;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildDatagridState(config, filters, statistics, 'Search websites...');

    const columns: DatagridColumn<WebsiteListItem>[] = [
        {
            key: 'name',
            header: 'Website',
            sortable: true,
            cell: (website) => (
                <div className="flex flex-col gap-1">
                    <Link
                        href={route('platform.websites.show', website.id)}
                        className="font-medium text-foreground hover:text-primary"
                    >
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
            cell: (website) => (
                <Badge variant="secondary">{website.agency_name}</Badge>
            ),
        },
        {
            key: 'server_name',
            header: 'Server',
            cell: (website) => (
                <Badge variant="outline">{website.server_name}</Badge>
            ),
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cell: (website) => (
                <Badge
                    variant={website.is_trashed ? 'destructive' : 'secondary'}
                >
                    {website.status_label}
                </Badge>
            ),
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
                action={route('platform.websites.index', {
                    status: currentStatus,
                })}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(website) => website.id}
                rowActions={(website) => mapRowActions(website.actions)}
                bulkActions={buildBulkActions(
                    config.actions,
                    config.settings.routePrefix,
                    currentStatus,
                )}
                empty={{
                    icon: <GlobeIcon className="size-5" />,
                    title: 'No websites found',
                    description:
                        'Create the first website to start automated provisioning and lifecycle tracking.',
                }}
                sorting={sorting}
                perPage={perPage}
                title="Provisioned websites"
                description="Monitor deployment status, DNS routing, and customer associations for every site."
            />
        </AppLayout>
    );
}
