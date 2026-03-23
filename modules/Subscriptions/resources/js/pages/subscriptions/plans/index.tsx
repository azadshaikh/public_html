import { Link, usePage } from '@inertiajs/react';
import { PlusIcon, TagIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState, buildScaffoldActionHandlers } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { PlanIndexPageProps, PlanListItem } from '../../../types/subscriptions';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Subscriptions', href: route('subscriptions.plans.index') },
    { title: 'Plans', href: route('subscriptions.plans.index') },
];

export default function PlansIndex({ config, rows, filters, statistics }: PlanIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addPlans;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search plans...',
    });

    const { rowActions, bulkActions } = buildScaffoldActionHandlers<PlanListItem>(config, {
        routePrefix: 'subscriptions.plans',
        currentStatus,
    });

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'delete')
        : bulkActions.filter((a) => a.key === 'delete');

    const columns: DatagridColumn<PlanListItem>[] = [
        {
            key: 'name',
            header: 'Plan Name',
            sortable: true,
            cell: (plan) => (
                <Link href={plan.show_url} className="font-medium text-foreground hover:underline">
                    {plan.name}
                </Link>
            ),
        },
        { key: 'prices_summary', header: 'Pricing' },
        { key: 'trial_days', header: 'Trial Days', sortable: true },
        { key: 'subscriptions_count', header: 'Subscribers', sortable: true },
        { key: 'is_active_label', header: 'Status', type: 'badge', badgeVariantKey: 'status_badge', sortable: true, sortKey: 'is_active' },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Plans"
            description="Manage subscription plans"
            headerActions={canAdd ? (
                <Button asChild>
                    <Link href={route('subscriptions.plans.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Plan
                    </Link>
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('subscriptions.plans.index')}
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
                    renderCard={(plan) => (
                        <div className="flex flex-col gap-3">
                            <Link href={plan.show_url} className="font-semibold text-foreground hover:underline">
                                {plan.name}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{plan.prices_summary}</div>
                                <div>{plan.subscriptions_count} subscriber{plan.subscriptions_count !== 1 ? 's' : ''}</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={plan.status_badge as Parameters<typeof Badge>[0]['variant']}>{plan.is_active_label}</Badge>
                                {plan.is_popular && <Badge variant="info">Popular</Badge>}
                            </div>
                        </div>
                    )}
                    empty={{ icon: <TagIcon />, title: 'No plans found', description: 'Create your first subscription plan to get started.' }}
                />
            </div>
        </AppLayout>
    );
}
