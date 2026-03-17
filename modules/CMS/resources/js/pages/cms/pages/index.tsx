import { Link, router, usePage } from '@inertiajs/react';
import {
    ExternalLinkIcon,
    FileIcon,
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
import type { PageIndexPageProps, PageListItem } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Pages', href: route('cms.pages.index') },
];

function getPageDateLabel(item: PageListItem): string {
    return item.status === 'published' && item.published_at_formatted
        ? 'Published'
        : 'Last Modified';
}

const FEATURED_IMAGE_COLUMN_CLASS = 'w-32 min-w-32';

function PagePreview({ page }: { page: PageListItem }) {
    if (page.featured_image_url) {
        return (
            <div className="flex h-20 w-32 shrink-0 overflow-hidden rounded-md border bg-muted">
                <img
                    src={page.featured_image_url}
                    alt={page.title}
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

function formatPermalinkLabel(
    permalinkUrl: string | null,
    slug: string,
): string {
    if (!permalinkUrl) {
        return `/${slug}`;
    }

    try {
        const parsedUrl = new URL(permalinkUrl, 'https://example.test');
        const label = `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`;

        return label === '' ? `/${slug}` : label;
    } catch {
        return permalinkUrl;
    }
}

function PageMeta({ page }: { page: PageListItem }) {
    const permalinkLabel = formatPermalinkLabel(page.permalink_url, page.slug);

    return (
        <div className="flex flex-wrap items-center gap-1.5 pt-1 text-sm sm:gap-2">
            {page.permalink_url ? (
                <a
                    href={page.permalink_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex max-w-[18rem] items-center gap-1 font-mono text-xs leading-none text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ExternalLinkIcon className="size-3.5 shrink-0" />
                    <span className="truncate leading-none">{permalinkLabel}</span>
                </a>
            ) : (
                <span className="inline-flex max-w-[18rem] items-center gap-1 font-mono text-xs leading-none text-muted-foreground">
                    <ExternalLinkIcon className="size-3.5 shrink-0" />
                    <span className="truncate leading-none">{permalinkLabel}</span>
                </span>
            )}
        </div>
    );
}

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
        buildScaffoldDatagridState(config, filters, statistics, {
            searchPlaceholder: 'Search pages...',
        });

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
            key: 'featured_image_url',
            header: '',
            headerClassName: FEATURED_IMAGE_COLUMN_CLASS,
            cellClassName: FEATURED_IMAGE_COLUMN_CLASS,
            cell: (item) => (
                <Link
                    href={item.edit_url}
                    className="block shrink-0 transition-opacity hover:opacity-80"
                >
                    <PagePreview page={item} />
                </Link>
            ),
        },
        {
            key: 'title',
            header: 'Title',
            headerClassName: 'w-[400px] min-w-[24rem]',
            cellClassName: 'w-[400px] min-w-[24rem]',
            sortable: true,
            cell: (item) => (
                <div className="min-w-0 space-y-1.5">
                    <div className="flex items-start gap-2">
                        <Link
                            href={item.edit_url}
                            className="line-clamp-2 font-semibold break-words text-foreground hover:underline"
                        >
                            {item.title}
                        </Link>
                    </div>

                    <PageMeta page={item} />
                </div>
            ),
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-[140px] text-center',
            cellClassName: 'w-[140px] text-center',
            type: 'badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'display_date',
            header: 'Date',
            headerClassName: 'w-[180px]',
            cellClassName: 'w-[180px]',
            sortable: true,
            sortKey: 'published_at',
            cell: (item) => (
                <div className="space-y-0.5 text-sm">
                    <div className="text-xs text-muted-foreground">
                        {getPageDateLabel(item)}
                    </div>
                    <div className="font-medium text-foreground">
                        {item.display_date}
                    </div>
                </div>
            ),
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
                    renderCard={(item) => (
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center gap-3">
                                <Link
                                    href={item.edit_url}
                                    className="shrink-0 transition-opacity hover:opacity-80"
                                >
                                    <PagePreview page={item} />
                                </Link>
                                <Link
                                    href={item.edit_url}
                                    className="flex min-w-0 flex-1 flex-col space-y-1.5 hover:opacity-80"
                                >
                                    <div className="flex items-start gap-2">
                                        <span className="line-clamp-2 font-semibold break-words text-foreground">
                                            {item.title}
                                        </span>
                                    </div>
                                    <PageMeta page={item} />
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
                                                (item.status === 'published'
                                                    ? 'success'
                                                    : item.status === 'draft'
                                                      ? 'warning'
                                                      : item.status === 'trash'
                                                        ? 'destructive'
                                                        : 'secondary') as Parameters<
                                                    typeof Badge
                                                >[0]['variant']
                                            }
                                        >
                                            {item.status_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {getPageDateLabel(item)}
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {item.display_date}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
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
