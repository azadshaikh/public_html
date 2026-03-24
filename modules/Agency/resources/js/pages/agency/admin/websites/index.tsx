import { Link } from '@inertiajs/react';
import { GlobeIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import {
    buildScaffoldActionHandlers,
    buildScaffoldDatagridState,
    buildScaffoldEmptyState,
} from '@/lib/scaffold-datagrid';
import type { BreadcrumbItem } from '@/types';
import type { ScaffoldIndexPageProps } from '@/types/scaffold';

type AgencyWebsiteRow = {
    id: number;
    name: string;
    domain: string | null;
    show_url: string;
    site_id: string | null;
    customer_name: string | null;
    type: string;
    type_label: string;
    plan: string | null;
    status: string;
    status_label: string;
    expired_on: string | null;
    created_at: string | null;
};

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'secondary' {
    switch (status.toLowerCase()) {
        case 'active':
            return 'success';
        case 'suspended':
            return 'warning';
        case 'failed':
        case 'expired':
        case 'trash':
            return 'danger';
        default:
            return 'secondary';
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Agency',
        href: route('agency.admin.websites.index', { status: 'all' }),
    },
    {
        title: 'Websites',
        href: route('agency.admin.websites.index', { status: 'all' }),
    },
];

export default function AgencyAdminWebsitesIndex({
    config,
    rows,
    filters,
    statistics,
    empty_state_config,
}: ScaffoldIndexPageProps<AgencyWebsiteRow>) {
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildScaffoldDatagridState(config, filters, statistics, {
            searchPlaceholder: 'Search agency websites...',
            perPageOptions: [10, 25, 50, 100],
        });
    const { rowActions, bulkActions } = buildScaffoldActionHandlers(config, {
        bulkActionUrl: route('agency.admin.websites.bulk-action'),
        currentStatus,
    });

    const columns: DatagridColumn<AgencyWebsiteRow>[] = [
        {
            key: 'site_id',
            header: 'Site ID',
            cell: (website) => (
                <Badge variant="secondary" className="font-mono text-[0.7rem]">
                    {website.site_id ?? 'N/A'}
                </Badge>
            ),
        },
        {
            key: 'name',
            header: 'Website',
            sortable: true,
            cell: (website) => (
                <div className="flex flex-col gap-1">
                    <Link
                        href={website.show_url}
                        className="font-medium text-foreground hover:text-primary"
                    >
                        {website.name}
                    </Link>
                    <span className="text-xs text-muted-foreground">
                        {website.domain ?? 'No domain'}
                    </span>
                </div>
            ),
        },
        {
            key: 'customer_name',
            header: 'Customer',
        },
        {
            key: 'type_label',
            header: 'Type',
            cell: (website) => (
                <Badge variant="outline">{website.type_label}</Badge>
            ),
        },
        {
            key: 'plan',
            header: 'Plan',
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cell: (website) => (
                <Badge variant={statusVariant(website.status)}>
                    {website.status_label}
                </Badge>
            ),
        },
        {
            key: 'expired_on',
            header: 'Expiry',
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
            title="Agency Websites"
            description="Review customer ownership, lifecycle status, and support-ready website metadata."
        >
            <Datagrid
                action={route('agency.admin.websites.index', {
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
                sorting={sorting}
                perPage={perPage}
                empty={buildScaffoldEmptyState(empty_state_config, {
                    icon: <GlobeIcon className="size-5" />,
                    fallbackTitle: 'No agency websites found',
                    fallbackDescription:
                        'Website records will appear here once onboarding or imports create them.',
                })}
            />
        </AppLayout>
    );
}
