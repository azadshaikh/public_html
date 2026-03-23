import { Link, router, usePage } from '@inertiajs/react';
import { PencilIcon, PercentIcon, PlusIcon, RefreshCwIcon, Trash2Icon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridAction, DatagridBulkAction, DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { TaxIndexPageProps, TaxListItem } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Taxes', href: route('app.billing.taxes.index') },
];

export default function TaxesIndex({ config, rows, filters, statistics }: TaxIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addTaxes;
    const canEdit = page.props.auth.abilities.editTaxes;
    const canDelete = page.props.auth.abilities.deleteTaxes;
    const canRestore = page.props.auth.abilities.restoreTaxes;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search taxes...',
    });

    const handleBulkAction = (action: string, selected: TaxListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(
            route('app.billing.taxes.bulk-action'),
            { action, ids: selected.map((i) => i.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<TaxListItem>[] = [
        {
            key: 'name',
            header: 'Name',
            sortable: true,
            cell: (tax) => (
                <Link href={tax.show_url} className="font-medium text-foreground hover:underline">
                    {tax.name}
                </Link>
            ),
        },
        {
            key: 'code',
            header: 'Code',
            headerClassName: 'w-[120px]',
            cellClassName: 'w-[120px] text-sm text-muted-foreground',
        },
        {
            key: 'formatted_rate',
            header: 'Rate',
            headerClassName: 'text-right',
            cellClassName: 'text-right text-sm font-medium',
        },
        {
            key: 'country',
            header: 'Country',
            headerClassName: 'w-[100px]',
            cellClassName: 'w-[100px] text-sm text-muted-foreground',
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
    ];

    const rowActions = (tax: TaxListItem): DatagridAction[] => {
        if ((tax as TaxListItem & { is_trashed?: boolean }).is_trashed) {
            return [
                ...(canRestore ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('app.billing.taxes.restore', tax.id), method: 'PATCH' as const, confirm: `Restore tax "${tax.name}"?` }] : []),
                ...(canDelete ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('app.billing.taxes.force-delete', tax.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete tax "${tax.name}"? This cannot be undone!`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEdit ? [{ label: 'Edit', href: route('app.billing.taxes.edit', tax.id), icon: <PencilIcon /> }] : []),
            ...(canDelete ? [{ label: 'Move to Trash', href: route('app.billing.taxes.destroy', tax.id), method: 'DELETE' as const, confirm: `Move tax "${tax.name}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<TaxListItem>[] = [
        ...(canDelete ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected taxes to trash?', onSelect: (items: TaxListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestore ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected taxes?', onSelect: (items: TaxListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDelete ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected taxes?', onSelect: (items: TaxListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Taxes"
            description="Manage tax rates"
            headerActions={canAdd ? (
                <Button asChild>
                    <Link href={route('app.billing.taxes.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Tax
                    </Link>
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.billing.taxes.index')}
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
                    renderCard={(tax) => (
                        <div className="flex flex-col gap-3">
                            <Link href={tax.show_url} className="font-semibold text-foreground hover:underline">
                                {tax.name}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{tax.code}</div>
                                <div className="font-medium text-foreground">{tax.formatted_rate}</div>
                                {(tax.country || tax.state) && (
                                    <div>{[tax.country, tax.state].filter(Boolean).join(', ')}</div>
                                )}
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={tax.status_badge as Parameters<typeof Badge>[0]['variant']}>{tax.status_label}</Badge>
                            </div>
                        </div>
                    )}
                    empty={{ icon: <PercentIcon />, title: 'No taxes found', description: 'Try a different filter or create your first tax rate.' }}
                />
            </div>
        </AppLayout>
    );
}
