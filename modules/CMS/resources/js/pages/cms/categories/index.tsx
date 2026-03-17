import { Link, router, usePage } from '@inertiajs/react';
import {
    ExternalLinkIcon,
    FolderIcon,
    ImageIcon,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { CategoryIndexPageProps, CategoryListItem } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Categories', href: route('cms.categories.index') },
];

function getCategoryDateLabel(): string {
    return 'Created';
}

function CategoryPreview({ category }: { category: CategoryListItem }) {
    if (category.featured_image_url) {
        return (
            <div className="flex h-20 w-32 shrink-0 overflow-hidden rounded-md border bg-muted">
                <img
                    src={category.featured_image_url}
                    alt={category.title}
                    className="h-full w-full object-cover"
                    loading="lazy"
                />
            </div>
        );
    }

    return (
        <div className="flex h-20 w-32 shrink-0 items-center justify-center rounded-md border bg-muted/50 text-muted-foreground">
            <ImageIcon className="size-6" />
        </div>
    );
}

function CategoryMeta({ category }: { category: CategoryListItem }) {
    return (
        <div className="flex flex-wrap items-center gap-1.5 pt-1 text-sm sm:gap-2">
            {category.permalink_url ? (
                <a
                    href={category.permalink_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex max-w-[18rem] items-center gap-1 font-mono text-xs leading-none text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ExternalLinkIcon className="size-3.5 shrink-0" />
                    <span className="truncate leading-none pt-1">/{category.slug}</span>
                </a>
            ) : (
                <span className="inline-flex max-w-[18rem] items-center gap-1 font-mono text-xs leading-none text-muted-foreground">
                    <ExternalLinkIcon className="size-3.5 shrink-0" />
                    <span className="truncate leading-none">/{category.slug}</span>
                </span>
            )}
            {category.parent_name ? (
                <span className="max-w-[250px] truncate text-xs text-muted-foreground">
                    in {category.parent_name}
                </span>
            ) : null}
        </div>
    );
}

export default function CategoriesIndex({
    config,
    rows,
    filters,
    statistics,
}: CategoryIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddCategories = page.props.auth.abilities.addCategories;
    const canEditCategories = page.props.auth.abilities.editCategories;
    const canDeleteCategories = page.props.auth.abilities.deleteCategories;
    const canRestoreCategories = page.props.auth.abilities.restoreCategories;
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildScaffoldDatagridState(config, filters, statistics, {
            searchPlaceholder: 'Search categories...',
        });

    const handleBulkAction = (
        action: string,
        selected: CategoryListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('cms.categories.bulk-action'),
            { action, ids: selected.map((c) => c.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<CategoryListItem>[] = [
        {
            key: 'title',
            header: 'Title',
            headerClassName: 'w-[42%] min-w-[26rem]',
            cellClassName: 'w-[42%] min-w-[26rem]',
            sortable: true,
            cell: (cat) => (
                <div className="flex min-w-0 items-center gap-4">
                    <Link
                        href={cat.edit_url}
                        className="shrink-0 transition-opacity hover:opacity-80"
                    >
                        <CategoryPreview category={cat} />
                    </Link>

                    <div className="min-w-0 flex-1 space-y-1.5">
                        <div className="flex items-start gap-2">
                            <Link
                                href={cat.edit_url}
                                className="line-clamp-2 font-semibold break-words text-foreground hover:underline"
                            >
                                {cat.title}
                            </Link>
                        </div>

                        <CategoryMeta category={cat} />
                    </div>
                </div>
            ),
        },
        {
            key: 'posts_count',
            header: 'Posts',
            headerClassName: 'w-24 text-center',
            cellClassName: 'w-24 text-center',
            sortable: true,
            cell: (cat) => (
                <Badge variant="secondary" className="bg-muted/50 font-normal">
                    {cat.posts_count}
                </Badge>
            ),
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
            key: 'display_date',
            header: 'Date',
            headerClassName: 'w-52',
            cellClassName: 'w-52',
            sortable: true,
            sortKey: 'created_at',
            cell: (cat) => (
                <div className="space-y-0.5 text-sm">
                    <div className="text-xs text-muted-foreground">
                        {getCategoryDateLabel(cat)}
                    </div>
                    <div className="font-medium text-foreground">
                        {cat.display_date}
                    </div>
                </div>
            ),
        },
    ];

    const rowActions = (cat: CategoryListItem): DatagridAction[] => {
        if (cat.is_trashed) {
            return [
                ...(canRestoreCategories
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route('cms.categories.restore', cat.id),
                              method: 'PATCH' as const,
                              confirm: `Restore "${cat.title}"?`,
                          },
                      ]
                    : []),
                ...(canDeleteCategories
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'cms.categories.force-delete',
                                  cat.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${cat.title}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEditCategories
                ? [{ label: 'Edit', href: cat.edit_url, icon: <PencilIcon /> }]
                : []),
            ...(canDeleteCategories
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('cms.categories.destroy', cat.id),
                          method: 'DELETE' as const,
                          confirm: `Move "${cat.title}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<CategoryListItem>[] = [
        ...(canDeleteCategories
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected categories to trash?',
                      onSelect: (
                          items: CategoryListItem[],
                          clear: () => void,
                      ) => handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestoreCategories
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected categories?',
                      onSelect: (
                          items: CategoryListItem[],
                          clear: () => void,
                      ) => handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDeleteCategories
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: '⚠️ Permanently delete selected categories?',
                      onSelect: (
                          items: CategoryListItem[],
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
            title="Categories"
            description="Organize posts into categories"
            headerActions={
                canAddCategories ? (
                    <Button asChild>
                        <Link href={route('cms.categories.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Category
                        </Link>
                    </Button>
                ) : undefined
            }
        >
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
                    sorting={sorting}
                    perPage={perPage}
                    view={{
                        value: (filters.view as 'table' | 'cards') ?? 'table',
                        storageKey: 'cms-categories-datagrid-view',
                    }}
                    renderCard={(cat) => (
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center gap-3">
                                <Link
                                    href={cat.edit_url}
                                    className="shrink-0 transition-opacity hover:opacity-80"
                                >
                                    <CategoryPreview category={cat} />
                                </Link>
                                <Link
                                    href={cat.edit_url}
                                    className="flex min-w-0 flex-1 flex-col space-y-1.5 hover:opacity-80"
                                >
                                    <div className="flex items-start gap-2">
                                        <span className="line-clamp-2 font-semibold break-words text-foreground">
                                            {cat.title}
                                        </span>
                                    </div>
                                    <CategoryMeta category={cat} />
                                </Link>
                            </div>
                            <div className="mt-auto grid gap-3 pt-2 sm:grid-cols-2">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                (cat.status === 'published'
                                                    ? 'success'
                                                    : cat.status === 'draft'
                                                      ? 'warning'
                                                      : cat.status === 'trash'
                                                        ? 'destructive'
                                                        : 'secondary') as Parameters<
                                                    typeof Badge
                                                >[0]['variant']
                                            }
                                        >
                                            {cat.status_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {getCategoryDateLabel(cat)}
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {cat.display_date}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    empty={{
                        icon: <FolderIcon />,
                        title: 'No categories found',
                        description:
                            'Try a different filter or create the first category.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
