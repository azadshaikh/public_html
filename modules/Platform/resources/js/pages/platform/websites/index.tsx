import { Link, usePage } from '@inertiajs/react';
import { GlobeIcon, PlusIcon } from 'lucide-react';
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
import type { PlatformIndexPageProps, WebsiteListItem } from '../../../types/platform';

function websiteStatusVariant(statusLabel: string, isTrashed?: boolean): 'success' | 'warning' | 'danger' | 'secondary' {
    if (isTrashed) {
        return 'danger';
    }

    switch (statusLabel.toLowerCase()) {
        case 'active':
            return 'success';
        case 'suspended':
            return 'warning';
        case 'expired':
        case 'failed':
        case 'deleted':
        case 'trash':
            return 'danger';
        default:
            return 'secondary';
    }
}

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
    empty_state_config,
}: PlatformIndexPageProps<WebsiteListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddWebsites = page.props.auth.abilities.addWebsites;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search websites...',
        perPageOptions: [15, 25, 50, 100],
    });
    const { rowActions, bulkActions } = buildScaffoldActionHandlers(config, {
        bulkActionUrl: route('platform.websites.bulk-action'),
        currentStatus,
    });

    const columns: DatagridColumn<WebsiteListItem>[] = [
        {
            key: 'uid',
            header: 'UID',
            sortable: true,
            cell: (website) => (
                <Badge variant="secondary" className="font-mono text-[0.7rem]">
                    {website.uid ?? '—'}
                </Badge>
            ),
        },
        {
            key: 'name',
            header: 'Name',
            sortable: true,
            cell: (website) => (
                <div className="flex flex-col gap-1">
                    <Link
                        href={route('platform.websites.show', website.id)}
                        className="font-medium text-foreground hover:text-primary"
                    >
                        {website.name}
                    </Link>
                    <span className="text-xs text-muted-foreground">{website.domain}</span>
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
                <Badge variant={websiteStatusVariant(website.status_label, website.is_trashed)}>
                    {website.status_label}
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
            title="Websites"
            description="Manage your websites and hosting."
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
                scaffoldColumns={config.columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(website) => website.id}
                rowActions={rowActions}
                bulkActions={bulkActions}
                empty={buildScaffoldEmptyState(empty_state_config, {
                    icon: <GlobeIcon className="size-5" />,
                    fallbackTitle: 'No websites found',
                    fallbackDescription:
                        'Create the first website to start automated provisioning and lifecycle tracking.',
                })}
                sorting={sorting}
                perPage={perPage}
                title="Websites"
                description="Review hosting placement, customer ownership, and lifecycle state across your sites."
            />
        </AppLayout>
    );
}
