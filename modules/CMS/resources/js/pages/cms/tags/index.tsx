import { Link, router, usePage } from '@inertiajs/react';
import {
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    TagIcon,
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
import { mapStatusTab } from '../../../lib/helpers';
import type { TagIndexPageProps, TagListItem } from '../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Tags', href: route('cms.tags.index') },
];

export default function TagsIndex({ config, rows, filters, statistics }: TagIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddTags = page.props.auth.abilities.addTags;
    const canEditTags = page.props.auth.abilities.editTags;
    const canDeleteTags = page.props.auth.abilities.deleteTags;
    const canRestoreTags = page.props.auth.abilities.restoreTags;

    const handleBulkAction = (action: string, selected: TagListItem[], clearSelection: () => void) => {
        if (selected.length === 0) return;
        router.post(route('cms.tags.bulk-action'), { action, ids: selected.map((t) => t.id), status: filters.status }, { preserveScroll: true, onSuccess: () => clearSelection() });
    };

    const gridFilters: DatagridFilter[] = [
        { type: 'search', name: 'search', value: filters.search, placeholder: 'Search tags...', className: 'lg:min-w-80' },
    ];

    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) => mapStatusTab(tab, statistics, filters.status));

    const columns: DatagridColumn<TagListItem>[] = [
        {
            key: 'title', header: 'Title', sortable: true,
            cell: (tag) => (
                <Link href={tag.edit_url} className="font-medium text-foreground hover:opacity-80">
                    {tag.title}
                </Link>
            ),
        },
        { key: 'posts_count', header: 'Posts', headerClassName: 'w-24 text-center', cellClassName: 'w-24 text-center text-sm text-muted-foreground', sortable: true },
        { key: 'status_label', header: 'Status', headerClassName: 'w-28 text-center', cellClassName: 'w-28 text-center', type: 'badge', sortable: true, sortKey: 'status' },
        { key: 'display_date', header: 'Date', headerClassName: 'w-36', cellClassName: 'w-36 text-sm text-muted-foreground', sortable: true, sortKey: 'created_at' },
    ];

    const rowActions = (tag: TagListItem): DatagridAction[] => {
        if (tag.is_trashed) {
            return [
                ...(canRestoreTags ? [{ label: 'Restore', icon: <RefreshCwIcon />, href: route('cms.tags.restore', tag.id), method: 'PATCH' as const, confirm: `Restore "${tag.title}"?` }] : []),
                ...(canDeleteTags ? [{ label: 'Delete Permanently', icon: <Trash2Icon />, href: route('cms.tags.force-delete', tag.id), method: 'DELETE' as const, confirm: `⚠️ Permanently delete "${tag.title}"? This cannot be undone!`, variant: 'destructive' as const }] : []),
            ];
        }
        return [
            ...(canEditTags ? [{ label: 'Edit', href: tag.edit_url, icon: <PencilIcon /> }] : []),
            ...(canDeleteTags ? [{ label: 'Move to Trash', href: route('cms.tags.destroy', tag.id), method: 'DELETE' as const, confirm: `Move "${tag.title}" to trash?`, icon: <Trash2Icon />, variant: 'destructive' as const }] : []),
        ];
    };

    const bulkActions: DatagridBulkAction<TagListItem>[] = [
        ...(canDeleteTags ? [{ key: 'bulk-delete', label: 'Move to Trash', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: 'Move selected tags to trash?', onSelect: (items: TagListItem[], clear: () => void) => handleBulkAction('delete', items, clear) }] : []),
        ...(canRestoreTags ? [{ key: 'bulk-restore', label: 'Restore', icon: <RefreshCwIcon />, confirm: 'Restore selected tags?', onSelect: (items: TagListItem[], clear: () => void) => handleBulkAction('restore', items, clear) }] : []),
        ...(canDeleteTags ? [{ key: 'bulk-force-delete', label: 'Delete Permanently', icon: <Trash2Icon />, variant: 'destructive' as const, confirm: '⚠️ Permanently delete selected tags?', onSelect: (items: TagListItem[], clear: () => void) => handleBulkAction('force_delete', items, clear) }] : []),
    ];

    const visibleBulkActions = filters.status === 'trash'
        ? bulkActions.filter((a) => a.key !== 'bulk-delete')
        : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout breadcrumbs={breadcrumbs} title="Tags" description="Organize posts with tags"
            headerActions={canAddTags ? (<Button asChild><Link href={route('cms.tags.create')}><PlusIcon data-icon="inline-start" />Add Tag</Link></Button>) : undefined}>
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.tags.index')}
                    rows={rows}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(tag) => tag.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={{ sort: filters.sort, direction: filters.direction }}
                    perPage={{ value: filters.per_page, options: [10, 25, 50, 100] }}
                    view={{ value: (filters.view as 'table' | 'cards') ?? 'table', storageKey: 'cms-tags-datagrid-view' }}
                    empty={{ icon: <TagIcon />, title: 'No tags found', description: 'Try a different filter or create the first tag.' }}
                />
            </div>
        </AppLayout>
    );
}
