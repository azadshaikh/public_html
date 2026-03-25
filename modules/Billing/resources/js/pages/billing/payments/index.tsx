import { Link, router, usePage } from '@inertiajs/react';
import { CreditCardIcon, PencilIcon, PlusIcon, RefreshCwIcon, Trash2Icon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridAction, DatagridBulkAction, DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { PaymentIndexPageProps, PaymentListItem } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Payments', href: route('app.billing.payments.index') },
];

export default function PaymentsIndex({ config, rows, filters, statistics }: PaymentIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addPayments;
    const canEdit = page.props.auth.abilities.editPayments;
    const canDelete = page.props.auth.abilities.deletePayments;
    const canRestore = page.props.auth.abilities.restorePayments;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics ?? {}, {
        searchPlaceholder: 'Search payments...',
    });

    const handleBulkAction = (action: string, selected: PaymentListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(
            route('app.billing.payments.bulk-action'),
            { action, ids: selected.map((i) => i.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<PaymentListItem>[] = [
        {
            key: 'payment_number',
            header: 'Payment #',
            sortable: true,
            cell: (payment) => (
                <Link href={payment.show_url} className="font-medium text-foreground hover:underline">
                    {payment.payment_number}
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
            key: 'formatted_amount',
            header: 'Amount',
            headerClassName: 'w-[120px] text-right',
            cellClassName: 'w-[120px] text-right text-sm font-medium',
            sortable: true,
            sortKey: 'amount',
        },
        {
            key: 'payment_method_label',
            header: 'Method',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'payment_method_badge',
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
            key: 'paid_at',
            header: 'Paid At',
            headerClassName: 'w-[120px]',
            cellClassName: 'w-[120px] text-sm text-muted-foreground',
            sortable: true,
        },
    ];

    const rowActions = (payment: PaymentListItem): DatagridAction[] => {
        if (payment.is_trashed) {
            return [
                ...(canRestore ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('app.billing.payments.restore', payment.id), method: 'PATCH' as const, confirm: `Restore payment "${payment.payment_number}"?` }] : []),
                ...(canDelete ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('app.billing.payments.force-delete', payment.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete payment "${payment.payment_number}"? This cannot be undone!`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEdit ? [{ label: 'Edit', href: route('app.billing.payments.edit', payment.id), icon: <PencilIcon /> }] : []),
            ...(canDelete ? [{ label: 'Move to Trash', href: route('app.billing.payments.destroy', payment.id), method: 'DELETE' as const, confirm: `Move payment "${payment.payment_number}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<PaymentListItem>[] = [
        ...(canDelete ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected payments to trash?', onSelect: (items: PaymentListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestore ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected payments?', onSelect: (items: PaymentListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDelete ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected payments?', onSelect: (items: PaymentListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Payments"
            description="Manage billing payments"
            headerActions={canAdd ? (
                <Button asChild>
                    <Link href={route('app.billing.payments.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Payment
                    </Link>
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.billing.payments.index')}
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
                    renderCard={(payment) => (
                        <div className="flex flex-col gap-3">
                            <Link href={payment.show_url} className="font-semibold text-foreground hover:underline">
                                {payment.payment_number}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{payment.customer_display}</div>
                                <div className="font-medium text-foreground">{payment.formatted_amount}</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={payment.payment_method_badge as Parameters<typeof Badge>[0]['variant']}>{payment.payment_method_label}</Badge>
                                <Badge variant={payment.status_badge as Parameters<typeof Badge>[0]['variant']}>{payment.status_label}</Badge>
                            </div>
                        </div>
                    )}
                    empty={{ icon: <CreditCardIcon />, title: 'No payments found', description: 'Try a different filter or create your first payment.' }}
                />
            </div>
        </AppLayout>
    );
}
