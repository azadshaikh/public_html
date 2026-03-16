import { Link, router, usePage } from '@inertiajs/react';
import {
    FolderIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    Trash2Icon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { mapFilters, mapStatusTab } from '../../../lib/helpers';
import type { CategoryIndexPageProps, CategoryListItem } from '../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Categories', href: route('cms.categories.index') },
];

export default function CategoriesIndex({ config, rows, filters, statistics }: CategoryIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddCategories = page.props.auth.abilities.addCategories;
    const canEditCategories = page.props.auth.abilities.editCategories;
    const canDeleteCategories = page.props.auth.abilities.deleteCategories;
    const canRestoreCategories = page.props.auth.abilities.restoreCategories;

    const handleBulkAction = (action: string, selected: CategoryListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(route('cms.categories.bulk-action'), { action, ids: selected.map((c) => c.id), status: filters.status }, { preserveScroll: true, onSuccess: () => clearSelection() });
    };

    const gridFilters = mapFilters(config.filters, filters, 'Search categories...');

    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) => mapStatusTab(tab, statistics, filters.status));

    const columns: DatagridColumn<CategoryListItem>[] = [
        {
            key: 'title', header: 'Title', sortable: true,
            cell: (cat) => (
                <Link href={cat.edit_url} className="flex min-w-0 flex-col gap-1 hover:opacity-80">
                    <span className="font-medium text-foreground">{cat.title}</span>
                    {cat.parent_name && <span className="text-xs text-muted-foreground">in {cat.parent_name}</span>}
                </Link>
            ),
        },
        { key: 'posts_count', header: 'Posts', headerClassName: 'w-24 text-center', cellClassName: 'w-24 text-center text-sm text-muted-foreground', sortable: true },
        { key: 'status_label', header: 'Status', headerClassName: 'w-28 text-center', cellClassName: 'w-28 text-center', type: 'badge', sortable: true, sortKey: 'status' },
        { key: 'display_date', header: 'Date', headerClassName: 'w-36', cellClassName: 'w-36 text-sm text-muted-foreground', sortable: true, sortKey: 'created_at' },
    ];

    const rowActions = (cat: CategoryListItem): DatagridAction[] => {
        if (cat.is_trashed) {
            return [
                ...(canRestoreCategories ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('cms.categories.restore', cat.id), method: 'PATCH' as const, confirm: `Restore "${cat.title}"?` }] : []),
                ...(canDeleteCategories ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('cms.categories.force-delete', cat.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete "${cat.title}"? This cannot be undone!`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEditCategories ? [{ label: 'Edit', href: cat.edit_url, icon: <PencilIcon /> }] : []),
            ...(canDeleteCategories ? [{ label: 'Move to Trash', href: route('cms.categories.destroy', cat.id), method: 'DELETE' as const, confirm: `Move "${cat.title}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<CategoryListItem>[] = [
        ...(canDeleteCategories ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected categories to trash?', onSelect: (items: CategoryListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestoreCategories ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected categories?', onSelect: (items: CategoryListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDeleteCategories ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected categories?', onSelect: (items: CategoryListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = filters.status === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout breadcrumbs={breadcrumbs} title="Categories" description="Organize posts into categories"
            headerActions={canAddCategories ? (<Button asChild><Link href={route('cms.categories.create')}><PlusIcon data-icon="inline-start" />Add Category</Link></Button>) : undefined}>
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.categories.index')}
                    rows={rows}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(cat) => cat.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={{ sort: filters.sort, direction: filters.direction }}
                    perPage={{ value: filters.per_page, options: [10, 25, 50, 100] }}
                    view={{ value: (filters.view as 'table' | 'cards') ?? 'table', storageKey: 'cms-categories-datagrid-view' }}
                    empty={{ icon: <FolderIcon />, title: 'No categories found', description: 'Try a different filter or create the first category.' }}
                />
            </div>
        </AppLayout>
    );
}
