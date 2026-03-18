import { Link, router, usePage } from '@inertiajs/react';
import {
    LayoutTemplateIcon,
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
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { DesignBlockIndexPageProps, DesignBlockListItem } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Design Blocks', href: route('cms.designblock.index') },
];

export default function DesignBlocksIndex({
    config,
    rows,
    filters,
    statistics,
}: DesignBlockIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddDesignBlocks = page.props.auth.abilities.addDesignBlocks;
    const canEditDesignBlocks = page.props.auth.abilities.editDesignBlocks;
    const canDeleteDesignBlocks = page.props.auth.abilities.deleteDesignBlocks;
    const canRestoreDesignBlocks = page.props.auth.abilities.restoreDesignBlocks;
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search design blocks...',
    });

    const handleBulkAction = (
        action: string,
        selected: DesignBlockListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('cms.designblock.bulk-action'),
            { action, ids: selected.map((d) => d.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<DesignBlockListItem>[] = [
        {
            key: 'title',
            header: 'Design Block',
            headerClassName: 'w-[42%] min-w-[24rem]',
            cellClassName: 'w-[42%] min-w-[24rem]',
            sortable: true,
            cell: (block) => (
                <div className="flex min-w-0 items-center gap-4">
                    <Link
                        href={block.edit_url}
                        className="shrink-0 transition-opacity hover:opacity-80"
                    >
                        <img
                            src={block.preview_image_url}
                            alt={block.title}
                            className="h-10 w-16 rounded border object-cover"
                            loading="lazy"
                        />
                    </Link>

                    <Link
                        href={block.edit_url}
                        className="min-w-0 font-medium text-foreground hover:opacity-80"
                    >
                        <span className="block truncate">{block.title}</span>
                    </Link>
                </div>
            ),
        },
        {
            key: 'design_type_label',
            header: 'Design Type',
            headerClassName: 'w-32 text-center',
            cellClassName: 'w-32 text-center',
            type: 'badge',
            sortable: true,
            sortKey: 'metadata->design_type',
        },
        {
            key: 'category_name',
            header: 'Category',
            headerClassName: 'w-36',
            cellClassName: 'w-36 text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'metadata->category',
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            type: 'badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'created_at',
            header: 'Created',
            headerClassName: 'w-32',
            cellClassName: 'w-32 text-sm text-muted-foreground',
            sortable: true,
        },
    ];

    const rowActions = (block: DesignBlockListItem): DatagridAction[] => {
        if (block.is_trashed) {
            return [
                ...(canRestoreDesignBlocks
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route('cms.designblock.restore', block.id),
                              method: 'PATCH' as const,
                              confirm: `Restore "${block.title}"?`,
                          },
                      ]
                    : []),
                ...(canDeleteDesignBlocks
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'cms.designblock.force-delete',
                                  block.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${block.title}"?`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEditDesignBlocks
                ? [
                      {
                          label: 'Edit',
                          href: block.edit_url,
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDeleteDesignBlocks
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('cms.designblock.destroy', block.id),
                          method: 'DELETE' as const,
                          confirm: `Move "${block.title}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<DesignBlockListItem>[] = [
        ...(canDeleteDesignBlocks
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected design blocks to trash?',
                      onSelect: (
                          items: DesignBlockListItem[],
                          clear: () => void,
                      ) => handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestoreDesignBlocks
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected design blocks?',
                      onSelect: (
                          items: DesignBlockListItem[],
                          clear: () => void,
                      ) => handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDeleteDesignBlocks
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: '⚠️ Permanently delete selected design blocks?',
                      onSelect: (
                          items: DesignBlockListItem[],
                          clear: () => void,
                      ) => handleBulkAction('force_delete', items, clear),
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
            title="Design Blocks"
            description="Reusable design components and sections"
            headerActions={
                canAddDesignBlocks ? (
                    <Button asChild>
                        <Link href={route('cms.designblock.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Design Block
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.designblock.index')}
                    rows={rows}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(block) => block.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    view={{
                        value: (filters.view as 'table' | 'cards') ?? 'table',
                        storageKey: 'cms-designblocks-datagrid-view',
                    }}
                    empty={{
                        icon: <LayoutTemplateIcon />,
                        title: 'No design blocks found',
                        description:
                            'Try a different filter or create the first design block.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
