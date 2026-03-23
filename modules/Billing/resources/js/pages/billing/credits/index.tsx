import { Link, router, usePage } from '@inertiajs/react';
import { BanknoteIcon, PencilIcon, PlusIcon, RefreshCwIcon, Trash2Icon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridAction, DatagridBulkAction, DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { CreditIndexPageProps, CreditListItem } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Credits', href: route('app.billing.credits.index') },
];

export default function CreditsIndex({ config, rows, filters, statistics }: CreditIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addCredits;
    const canEdit = page.props.auth.abilities.editCredits;
    const canDelete = page.props.auth.abilities.deleteCredits;
    const canRestore = page.props.auth.abilities.restoreCredits;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search credits...',
    });

    const handleBulkAction = (action: string, selected: CreditListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(
            route('app.billing.credits.bulk-action'),
            { action, ids: selected.map((i) => i.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<CreditListItem>[] = [
        {
            key: 'credit_number',
            header: 'Credit #',
            sortable: true,
            cell: (credit) => (
                <Link href={credit.show_url} className="font-medium text-foreground hover:underline">
                    {credit.credit_number}
                </Link>
            ),
        },
        {
            key: 'customer_display',
            header: 'Customer',
            headerClassName: 'w-[200px]',
            cellClassName: 'w-[200px] text-sm text-muted-foreground',
            sortable: true,
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
            key: 'formatted_remaining',
            header: 'Remaining',
            headerClassName: 'w-[120px] text-right',
            cellClassName: 'w-[120px] text-right text-sm',
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
    ];

    const rowActions = (credit: CreditListItem): DatagridAction[] => {
        if ((credit as CreditListItem & { is_trashed?: boolean }).is_trashed) {
            return [
                ...(canRestore ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('app.billing.credits.restore', credit.id), method: 'PATCH' as const, confirm: `Restore credit "${credit.credit_number}"?` }] : []),
                ...(canDelete ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('app.billing.credits.force-delete', credit.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete credit "${credit.credit_number}"? This cannot be undone!`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEdit ? [{ label: 'Edit', href: route('app.billing.credits.edit', credit.id), icon: <PencilIcon /> }] : []),
            ...(canDelete ? [{ label: 'Move to Trash', href: route('app.billing.credits.destroy', credit.id), method: 'DELETE' as const, confirm: `Move credit "${credit.credit_number}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<CreditListItem>[] = [
        ...(canDelete ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected credits to trash?', onSelect: (items: CreditListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestore ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected credits?', onSelect: (items: CreditListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDelete ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected credits?', onSelect: (items: CreditListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Credits"
            description="Manage billing credits"
            headerActions={canAdd ? (
                <Button asChild>
                    <Link href={route('app.billing.credits.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Credit
                    </Link>
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.billing.credits.index')}
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
                    renderCard={(credit) => (
                        <div className="flex flex-col gap-3">
                            <Link href={credit.show_url} className="font-semibold text-foreground hover:underline">
                                {credit.credit_number}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{credit.customer_display}</div>
                                <div className="font-medium text-foreground">{credit.formatted_amount}</div>
                                <div>{credit.formatted_remaining} remaining</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={credit.type_badge as Parameters<typeof Badge>[0]['variant']}>{credit.type_label}</Badge>
                                <Badge variant={credit.status_badge as Parameters<typeof Badge>[0]['variant']}>{credit.status_label}</Badge>
                            </div>
                        </div>
                    )}
                    empty={{ icon: <BanknoteIcon />, title: 'No credits found', description: 'Try a different filter or create your first credit.' }}
                />
            </div>
        </AppLayout>
    );
}
