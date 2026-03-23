import { Link, router, usePage } from '@inertiajs/react';
import { PackageIcon, RefreshCwIcon, Trash2Icon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridAction, DatagridBulkAction, DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { OrderIndexPageProps, OrderListItem } from '../../types/orders';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Orders', href: route('app.orders.index') },
];

export default function OrdersIndex({ config, rows, filters, statistics }: OrderIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canDelete = page.props.auth.abilities.deleteOrders;
    const canRestore = page.props.auth.abilities.restoreOrders;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search orders...',
    });

    const handleBulkAction = (action: string, selected: OrderListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(
            route('app.orders.bulk-action'),
            { action, ids: selected.map((o) => o.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<OrderListItem>[] = [
        {
            key: 'order_number',
            header: 'Order #',
            sortable: true,
            cell: (order) => (
                <Link href={order.show_url} className="font-medium text-foreground hover:underline">
                    {order.order_number}
                </Link>
            ),
        },
        {
            key: 'customer_display',
            header: 'Customer',
            sortable: true,
            sortKey: 'customer_name',
            cellClassName: 'text-sm text-muted-foreground',
        },
        {
            key: 'type_label',
            header: 'Type',
            type: 'badge',
            badgeVariantKey: 'type_badge',
            sortable: true,
            sortKey: 'type',
        },
        {
            key: 'status_label',
            header: 'Status',
            type: 'badge',
            badgeVariantKey: 'status_badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'total_display',
            header: 'Total',
            headerClassName: 'text-right',
            cellClassName: 'text-right text-sm font-medium',
            sortable: true,
            sortKey: 'total',
        },
        {
            key: 'paid_at_formatted',
            header: 'Paid At',
            cellClassName: 'text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'paid_at',
        },
        {
            key: 'created_at_formatted',
            header: 'Created',
            cellClassName: 'text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'created_at',
        },
    ];

    const rowActions = (order: OrderListItem): DatagridAction[] => {
        if (order.is_trashed) {
            return [
                ...(canRestore ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('app.orders.restore', order.id), method: 'PATCH' as const, confirm: `Restore order "${order.order_number}"?` }] : []),
                ...(canDelete ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('app.orders.force-delete', order.id), method: 'DELETE' as const, confirm: `Permanently delete order "${order.order_number}"? This cannot be undone.`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canDelete ? [{ label: 'Move to Trash', href: route('app.orders.destroy', order.id), method: 'DELETE' as const, confirm: `Move order "${order.order_number}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<OrderListItem>[] = [
        ...(canDelete ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected orders to trash?', onSelect: (items: OrderListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestore ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected orders?', onSelect: (items: OrderListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDelete ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Permanently delete selected orders?', onSelect: (items: OrderListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Orders"
            description="View and manage orders"
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.orders.index')}
                    rows={rows}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(o) => o.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    renderCard={(order) => (
                        <div className="flex flex-col gap-3">
                            <Link href={order.show_url} className="font-semibold text-foreground hover:underline">
                                {order.order_number}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{order.customer_display}</div>
                                <div className="font-medium text-foreground">{order.total_display}</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={order.type_badge as Parameters<typeof Badge>[0]['variant']}>{order.type_label}</Badge>
                                <Badge variant={order.status_badge as Parameters<typeof Badge>[0]['variant']}>{order.status_label}</Badge>
                            </div>
                        </div>
                    )}
                    empty={{ icon: <PackageIcon />, title: 'No orders found', description: 'Orders will appear here when customers make purchases.' }}
                />
            </div>
        </AppLayout>
    );
}
