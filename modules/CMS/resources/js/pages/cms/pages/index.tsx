import { Link, router, usePage } from '@inertiajs/react';
import {
    FileIcon,
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
} from '@/components/datagrid/datagrid';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { buildDatagridState } from '../../../lib/helpers';
import type { PageIndexPageProps, PageListItem } from '../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Pages', href: route('cms.pages.index') },
];

export default function PagesIndex({
    config,
    rows,
    filters,
    statistics,
}: PageIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddPages = page.props.auth.abilities.addPages;
    const canEditPages = page.props.auth.abilities.editPages;
    const canDeletePages = page.props.auth.abilities.deletePages;
    const canRestorePages = page.props.auth.abilities.restorePages;
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildDatagridState(config, filters, statistics, 'Search pages...');

    const handleBulkAction = (
        action: string,
        selected: PageListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('cms.pages.bulk-action'),
            { action, ids: selected.map((p) => p.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<PageListItem>[] = [
        {
            key: 'title',
            header: 'Title',
            sortable: true,
            cell: (item) => (
                <Link
                    href={item.edit_url}
                    className="flex min-w-0 flex-col gap-1 hover:opacity-80"
                >
                    <span className="font-medium text-foreground">
                        {item.title}
                    </span>
                    {item.parent_name && (
                        <span className="text-xs text-muted-foreground">
                            in {item.parent_name}
                        </span>
                    )}
                </Link>
            ),
        },
        {
            key: 'author_name',
            header: 'Author',
            cellClassName: 'w-32 text-sm text-muted-foreground',
            headerClassName: 'w-32',
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-32 text-center',
            cellClassName: 'w-32 text-center',
            type: 'badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'display_date',
            header: 'Date',
            headerClassName: 'w-36',
            cellClassName: 'w-36 text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'published_at',
        },
    ];

    const rowActions = (item: PageListItem): DatagridAction[] => {
        if (item.is_trashed) {
            return [
                ...(canRestorePages
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route('cms.pages.restore', item.id),
                              method: 'PATCH' as const,
                              confirm: `Restore "${item.title}"?`,
                          },
                      ]
                    : []),
                ...(canDeletePages
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route('cms.pages.force-delete', item.id),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${item.title}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEditPages
                ? [{ label: 'Edit', href: item.edit_url, icon: <PencilIcon /> }]
                : []),
            ...(canDeletePages
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('cms.pages.destroy', item.id),
                          method: 'DELETE' as const,
                          confirm: `Move "${item.title}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<PageListItem>[] = [
        ...(canDeletePages
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected pages to trash?',
                      onSelect: (items: PageListItem[], clear: () => void) =>
                          handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestorePages
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected pages from trash?',
                      onSelect: (items: PageListItem[], clear: () => void) =>
                          handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDeletePages
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected pages? This cannot be undone!',
                      onSelect: (items: PageListItem[], clear: () => void) =>
                          handleBulkAction('force_delete', items, clear),
                  },
              ]
            : []),
    ];

    const visibleBulkActions =
        currentStatus === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Pages"
            description="Manage static pages"
            headerActions={
                canAddPages ? (
                    <Button asChild>
                        <Link href={route('cms.pages.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Page
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.pages.index')}
                    rows={rows}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(item) => item.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    view={{
                        value: (filters.view as 'table' | 'cards') ?? 'table',
                        storageKey: 'cms-pages-datagrid-view',
                    }}
                    empty={{
                        icon: <FileIcon />,
                        title: 'No pages found',
                        description:
                            'Try a different filter or create the first page.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
