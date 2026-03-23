import { Link, router, usePage } from '@inertiajs/react';
import { PencilIcon, PlusIcon, RefreshCwIcon, TagIcon, Trash2Icon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridAction, DatagridBulkAction, DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { CouponIndexPageProps, CouponListItem } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Coupons', href: route('app.billing.coupons.index') },
];

export default function CouponsIndex({ config, rows, filters, statistics }: CouponIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addCoupons;
    const canEdit = page.props.auth.abilities.editCoupons;
    const canDelete = page.props.auth.abilities.deleteCoupons;
    const canRestore = page.props.auth.abilities.restoreCoupons;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search coupons...',
    });

    const handleBulkAction = (action: string, selected: CouponListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(
            route('app.billing.coupons.bulk-action'),
            { action, ids: selected.map((i) => i.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<CouponListItem>[] = [
        {
            key: 'code',
            header: 'Code',
            sortable: true,
            cell: (coupon) => (
                <Link href={coupon.show_url} className="font-medium text-foreground hover:underline">
                    {coupon.code}
                </Link>
            ),
        },
        {
            key: 'name',
            header: 'Name',
            sortable: true,
        },
        {
            key: 'value_display',
            header: 'Value',
        },
        {
            key: 'discount_duration_label',
            header: 'Duration',
            type: 'badge',
            badgeVariantKey: 'discount_duration_badge',
        },
        {
            key: 'uses_count',
            header: 'Uses',
            cell: (coupon) => `${coupon.uses_count} / ${coupon.max_uses_display}`,
        },
        {
            key: 'is_active_label',
            header: 'Active',
            type: 'badge',
            badgeVariantKey: 'is_active_badge',
            sortable: true,
            sortKey: 'is_active',
        },
    ];

    const rowActions = (coupon: CouponListItem): DatagridAction[] => {
        if ((coupon as CouponListItem & { is_trashed?: boolean }).is_trashed) {
            return [
                ...(canRestore ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('app.billing.coupons.restore', coupon.id), method: 'PATCH' as const, confirm: `Restore coupon "${coupon.code}"?` }] : []),
                ...(canDelete ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('app.billing.coupons.force-delete', coupon.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete coupon "${coupon.code}"? This cannot be undone!`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEdit ? [{ label: 'Edit', href: route('app.billing.coupons.edit', coupon.id), icon: <PencilIcon /> }] : []),
            ...(canDelete ? [{ label: 'Move to Trash', href: route('app.billing.coupons.destroy', coupon.id), method: 'DELETE' as const, confirm: `Move coupon "${coupon.code}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<CouponListItem>[] = [
        ...(canDelete ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected coupons to trash?', onSelect: (items: CouponListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestore ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected coupons?', onSelect: (items: CouponListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDelete ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected coupons?', onSelect: (items: CouponListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = currentStatus === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Coupons"
            description="Manage discount coupons"
            headerActions={canAdd ? (
                <Button asChild>
                    <Link href={route('app.billing.coupons.create')}>
                        <PlusIcon data-icon="inline-start" />
                        New Coupon
                    </Link>
                </Button>
            ) : undefined}
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.billing.coupons.index')}
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
                    renderCard={(coupon) => (
                        <div className="flex flex-col gap-3">
                            <Link href={coupon.show_url} className="font-semibold text-foreground hover:underline">
                                {coupon.code}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{coupon.name}</div>
                                <div className="font-medium text-foreground">{coupon.value_display}</div>
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge variant={coupon.type_badge as Parameters<typeof Badge>[0]['variant']}>{coupon.type_label}</Badge>
                                <Badge variant={coupon.discount_duration_badge as Parameters<typeof Badge>[0]['variant']}>{coupon.discount_duration_label}</Badge>
                                <Badge variant={coupon.is_active_badge as Parameters<typeof Badge>[0]['variant']}>{coupon.is_active_label}</Badge>
                            </div>
                        </div>
                    )}
                    empty={{ icon: <TagIcon />, title: 'No coupons found', description: 'Try a different filter or create your first coupon.' }}
                />
            </div>
        </AppLayout>
    );
}
