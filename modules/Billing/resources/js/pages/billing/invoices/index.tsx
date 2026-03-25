import { Link, router, usePage } from '@inertiajs/react';
import { FileTextIcon, PencilIcon, PlusIcon, RefreshCwIcon, Trash2Icon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridAction, DatagridBulkAction, DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { InvoiceIndexPageProps, InvoiceListItem } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Invoices', href: route('app.billing.invoices.index') },
];

export default function InvoicesIndex({ config, rows, filters, statistics }: InvoiceIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addInvoices;
    const canEdit = page.props.auth.abilities.editInvoices;
    const canDelete = page.props.auth.abilities.deleteInvoices;
    const canRestore = page.props.auth.abilities.restoreInvoices;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics ?? {}, {
        searchPlaceholder: 'Search invoices...',
    });

    const handleBulkAction = (action: string, selected: InvoiceListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(
            route('app.billing.invoices.bulk-action'),
            { action, ids: selected.map((i) => i.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<InvoiceListItem>[] = [
        {
            key: 'invoice_number',
            header: 'Invoice #',
            sortable: true,
            cell: (invoice) => (
                <Link href={invoice.show_url} className="font-medium text-foreground hover:underline">
                    {invoice.invoice_number}
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
            key: 'formatted_total',
            header: 'Total',
            headerClassName: 'w-[120px] text-right',
            cellClassName: 'w-[120px] text-right text-sm font-medium',
            sortable: true,
            sortKey: 'total',
        },
        {
            key: 'formatted_amount_due',
            header: 'Amount Due',
            headerClassName: 'w-[120px] text-right',
            cellClassName: 'w-[120px] text-right text-sm',
            sortable: true,
            sortKey: 'amount_due',
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
            key: 'payment_status_label',
            header: 'Payment',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'payment_status_badge',
            sortable: true,
            sortKey: 'payment_status',
        },
        {
            key: 'due_date',
            header: 'Due Date',
            headerClassName: 'w-[120px]',
            cellClassName: 'w-[120px] text-sm text-muted-foreground',
            sortable: true,
        },
    ];

    const rowActions = (invoice: InvoiceListItem): DatagridAction[] => {
        if (invoice.is_trashed) {
            return [
                ...(canRestore ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('app.billing.invoices.restore', invoice.id), method: 'PATCH' as const, confirm: `Restore invoice "${invoice.invoice_number}"?` }] : []),
                ...(canDelete ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('app.billing.invoices.force-delete', invoice.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete invoice "${invoice.invoice_number}"? This cannot be undone!`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEdit ? [{ label: 'Edit', href: route('app.billing.invoices.edit', invoice.id), icon: <PencilIcon /> }] : []),
            ...(canDelete ? [{ label: 'Move to Trash', href: route('app.billing.invoices.destroy', invoice.id), method: 'DELETE' as const, confirm: `Move invoice "${invoice.invoice_number}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<InvoiceListItem>[] = [
        ...(canDelete ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected invoices to trash?', onSelect: (items: InvoiceListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestore ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected invoices?', onSelect: (items: InvoiceListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDelete ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected invoices?', onSelect: (items: InvoiceListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Invoices"
            description="Manage billing invoices"
            headerActions={canAdd ? (
                <Button asChild>
                    <Link href={route('app.billing.invoices.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Invoice
                    </Link>
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.billing.invoices.index')}
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
                    renderCard={(invoice) => (
                        <div className="flex flex-col gap-3">
                            <Link href={invoice.show_url} className="font-semibold text-foreground hover:underline">
                                {invoice.invoice_number}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{invoice.customer_display}</div>
                                <div className="font-medium text-foreground">{invoice.formatted_total}</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={invoice.status_badge as Parameters<typeof Badge>[0]['variant']}>{invoice.status_label}</Badge>
                                <Badge variant={invoice.payment_status_badge as Parameters<typeof Badge>[0]['variant']}>{invoice.payment_status_label}</Badge>
                            </div>
                        </div>
                    )}
                    empty={{ icon: <FileTextIcon />, title: 'No invoices found', description: 'Try a different filter or create your first invoice.' }}
                />
            </div>
        </AppLayout>
    );
}
