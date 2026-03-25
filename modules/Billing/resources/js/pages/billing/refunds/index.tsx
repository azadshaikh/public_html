import { Link, router, usePage } from '@inertiajs/react';
import { PencilIcon, PlusIcon, RefreshCwIcon, RotateCcwIcon, Trash2Icon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridAction, DatagridBulkAction, DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { RefundIndexPageProps, RefundListItem } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Refunds', href: route('app.billing.refunds.index') },
];

export default function RefundsIndex({ config, rows, filters, statistics }: RefundIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addRefunds;
    const canEdit = page.props.auth.abilities.editRefunds;
    const canDelete = page.props.auth.abilities.deleteRefunds;
    const canRestore = page.props.auth.abilities.restoreRefunds;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics ?? {}, {
        searchPlaceholder: 'Search refunds...',
    });

    const handleBulkAction = (action: string, selected: RefundListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(
            route('app.billing.refunds.bulk-action'),
            { action, ids: selected.map((i) => i.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<RefundListItem>[] = [
        {
            key: 'refund_number',
            header: 'Refund #',
            sortable: true,
            cell: (refund) => (
                <Link href={refund.show_url} className="font-medium text-foreground hover:underline">
                    {refund.refund_number}
                </Link>
            ),
        },
        {
            key: 'customer_display',
            header: 'Customer',
            headerClassName: 'w-[200px]',
            cellClassName: 'w-[200px] text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'customer_name',
        },
        {
            key: 'payment_number',
            header: 'Payment',
            headerClassName: 'w-[140px]',
            cellClassName: 'w-[140px] text-sm text-muted-foreground',
        },
        {
            key: 'formatted_amount',
            header: 'Amount',
            headerClassName: 'w-[120px] text-right',
            cellClassName: 'w-[120px] text-right text-sm font-medium',
            sortable: true,
            sortKey: 'amount',
        },
        {
            key: 'type_label',
            header: 'Type',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'type_badge',
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'status_badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'refunded_at',
            header: 'Refunded At',
            headerClassName: 'w-[120px]',
            cellClassName: 'w-[120px] text-sm text-muted-foreground',
            sortable: true,
        },
    ];

    const rowActions = (refund: RefundListItem): DatagridAction[] => {
        if ((refund as RefundListItem & { is_trashed?: boolean }).is_trashed) {
            return [
                ...(canRestore ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('app.billing.refunds.restore', refund.id), method: 'PATCH' as const, confirm: `Restore refund "${refund.refund_number}"?` }] : []),
                ...(canDelete ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('app.billing.refunds.force-delete', refund.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete refund "${refund.refund_number}"? This cannot be undone!`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEdit ? [{ label: 'Edit', href: route('app.billing.refunds.edit', refund.id), icon: <PencilIcon /> }] : []),
            ...(canDelete ? [{ label: 'Move to Trash', href: route('app.billing.refunds.destroy', refund.id), method: 'DELETE' as const, confirm: `Move refund "${refund.refund_number}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<RefundListItem>[] = [
        ...(canDelete ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected refunds to trash?', onSelect: (items: RefundListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestore ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected refunds?', onSelect: (items: RefundListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDelete ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected refunds?', onSelect: (items: RefundListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Refunds"
            description="Manage billing refunds"
            headerActions={canAdd ? (
                <Button asChild>
                    <Link href={route('app.billing.refunds.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Refund
                    </Link>
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.billing.refunds.index')}
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
                    renderCard={(refund) => (
                        <div className="flex flex-col gap-3">
                            <Link href={refund.show_url} className="font-semibold text-foreground hover:underline">
                                {refund.refund_number}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{refund.customer_display}</div>
                                <div>{refund.payment_number}</div>
                                <div className="font-medium text-foreground">{refund.formatted_amount}</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={refund.type_badge as Parameters<typeof Badge>[0]['variant']}>{refund.type_label}</Badge>
                                <Badge variant={refund.status_badge as Parameters<typeof Badge>[0]['variant']}>{refund.status_label}</Badge>
                            </div>
                        </div>
                    )}
                    empty={{ icon: <RotateCcwIcon />, title: 'No refunds found', description: 'Try a different filter or create your first refund.' }}
                />
            </div>
        </AppLayout>
    );
}
