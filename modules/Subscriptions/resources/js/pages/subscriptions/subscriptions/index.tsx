import { Link, usePage } from '@inertiajs/react';
import { PlusIcon, UsersIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState, buildScaffoldActionHandlers } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { SubscriptionIndexPageProps, SubscriptionListItem } from '../../../types/subscriptions';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Subscriptions', href: route('subscriptions.plans.index') },
    { title: 'Subscriptions', href: route('subscriptions.subscriptions.index') },
];

export default function SubscriptionsIndex({ config, rows, filters, statistics }: SubscriptionIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addSubscriptions;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search subscriptions...',
    });

    const { rowActions, bulkActions } = buildScaffoldActionHandlers<SubscriptionListItem>(config, {
        routePrefix: 'subscriptions.subscriptions',
        currentStatus,
    });

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'delete')
        : bulkActions.filter((a) => a.key === 'delete');

    const columns: DatagridColumn<SubscriptionListItem>[] = [
        {
            key: 'unique_id',
            header: 'ID',
            sortable: true,
            cell: (sub) => (
                <Link href={sub.show_url} className="font-medium text-foreground hover:underline">
                    {sub.unique_id}
                </Link>
            ),
        },
        {
            key: 'subscriber_name',
            header: 'Customer',
            cell: (sub) =>
                sub.subscriber_url ? (
                    <Link href={sub.subscriber_url} className="hover:underline">
                        {sub.subscriber_name}
                    </Link>
                ) : (
                    sub.subscriber_name
                ),
        },
        { key: 'plan_name', header: 'Plan', sortable: true },
        { key: 'formatted_price', header: 'Price' },
        { key: 'billing_cycle', header: 'Cycle' },
        { key: 'current_period_end_formatted', header: 'Renewal', type: 'text' },
        { key: 'status_label', header: 'Status', type: 'badge', badgeVariantKey: 'status_badge', sortable: true, sortKey: 'status' },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Subscriptions"
            description="Manage customer subscriptions"
            headerActions={canAdd ? (
                <Button asChild>
                    <Link href={route('subscriptions.subscriptions.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Subscription
                    </Link>
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('subscriptions.subscriptions.index')}
                    rows={rows}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(i) => i.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    renderCard={(sub) => (
                        <div className="flex flex-col gap-3">
                            <Link href={sub.show_url} className="font-semibold text-foreground hover:underline">
                                {sub.unique_id}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{sub.subscriber_name} &middot; {sub.plan_name}</div>
                                <div>{sub.formatted_price} / {sub.billing_cycle}</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={sub.status_badge as Parameters<typeof Badge>[0]['variant']}>{sub.status_label}</Badge>
                                {sub.on_trial && <Badge variant="info">Trial</Badge>}
                            </div>
                        </div>
                    )}
                    empty={{ icon: <UsersIcon />, title: 'No subscriptions found', description: 'Create your first subscription to get started.' }}
                />
            </div>
        </AppLayout>
    );
}
