import { Link, router, usePage } from '@inertiajs/react';
import {
    ExternalLinkIcon,
    ImageIcon,
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
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { TagIndexPageProps, TagListItem } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Tags', href: route('cms.tags.index') },
];

function getTagDateLabel(): string {
    return 'Created';
}

function TagPreview({ tag }: { tag: TagListItem }) {
    if (tag.featured_image_url) {
        return (
            <div className="flex h-20 w-32 shrink-0 overflow-hidden rounded-md border bg-muted">
                <img
                    src={tag.featured_image_url}
                    alt={tag.title}
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

function TagMeta({ tag }: { tag: TagListItem }) {
    const permalinkLabel = formatPermalinkLabel(tag.permalink_url, tag.slug);

    return (
        <div className="flex flex-wrap items-center gap-1.5 pt-1 text-sm sm:gap-2">
            {tag.permalink_url ? (
                <a
                    href={tag.permalink_url}
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

export default function TagsIndex({
    config,
    rows,
    filters,
    statistics,
}: TagIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddTags = page.props.auth.abilities.addTags;
    const canEditTags = page.props.auth.abilities.editTags;
    const canDeleteTags = page.props.auth.abilities.deleteTags;
    const canRestoreTags = page.props.auth.abilities.restoreTags;
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildScaffoldDatagridState(config, filters, statistics, {
            searchPlaceholder: 'Search tags...',
        });

    const handleBulkAction = (
        action: string,
        selected: TagListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('cms.tags.bulk-action'),
            { action, ids: selected.map((t) => t.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<TagListItem>[] = [
        {
            key: 'title',
            header: 'Title',
            headerClassName: 'w-[42%] min-w-[26rem]',
            cellClassName: 'w-[42%] min-w-[26rem]',
            sortable: true,
            cell: (tag) => (
                <div className="flex min-w-0 items-center gap-4">
                    <Link
                        href={tag.edit_url}
                        className="shrink-0 transition-opacity hover:opacity-80"
                    >
                        <TagPreview tag={tag} />
                    </Link>

                    <div className="min-w-0 flex-1 space-y-1.5">
                        <div className="flex items-start gap-2">
                            <Link
                                href={tag.edit_url}
                                className="line-clamp-2 font-semibold break-words text-foreground hover:underline"
                            >
                                {tag.title}
                            </Link>
                        </div>

                        <TagMeta tag={tag} />
                    </div>
                </div>
            ),
        },
        {
            key: 'posts_count',
            header: 'Posts',
            headerClassName: 'w-[100px] text-center',
            cellClassName: 'w-[100px] text-center',
            sortable: true,
            cell: (tag) => (
                <Badge variant="secondary" className="bg-muted/50 font-normal">
                    {tag.posts_count}
                </Badge>
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
            sortKey: 'created_at',
            cell: (tag) => (
                <div className="space-y-0.5 text-sm">
                    <div className="text-xs text-muted-foreground">
                        {getTagDateLabel()}
                    </div>
                    <div className="font-medium text-foreground">
                        {tag.display_date}
                    </div>
                </div>
            ),
        },
    ];

    const rowActions = (tag: TagListItem): DatagridAction[] => {
        if (tag.is_trashed) {
            return [
                ...(canRestoreTags
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route('cms.tags.restore', tag.id),
                              method: 'PATCH' as const,
                              confirm: `Restore "${tag.title}"?`,
                          },
                      ]
                    : []),
                ...(canDeleteTags
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route('cms.tags.force-delete', tag.id),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${tag.title}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEditTags
                ? [{ label: 'Edit', href: tag.edit_url, icon: <PencilIcon /> }]
                : []),
            ...(canDeleteTags
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('cms.tags.destroy', tag.id),
                          method: 'DELETE' as const,
                          confirm: `Move "${tag.title}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<TagListItem>[] = [
        ...(canDeleteTags
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected tags to trash?',
                      onSelect: (items: TagListItem[], clear: () => void) =>
                          handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestoreTags
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected tags?',
                      onSelect: (items: TagListItem[], clear: () => void) =>
                          handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDeleteTags
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: '⚠️ Permanently delete selected tags?',
                      onSelect: (items: TagListItem[], clear: () => void) =>
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
            title="Tags"
            description="Organize posts with tags"
            headerActions={
                canAddTags ? (
                    <Button asChild>
                        <Link href={route('cms.tags.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Tag
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.tags.index')}
                    rows={rows}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(tag) => tag.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    view={{
                        value: (filters.view as 'table' | 'cards') ?? 'table',
                        storageKey: 'cms-tags-datagrid-view',
                    }}
                    renderCard={(tag) => (
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center gap-3">
                                <Link
                                    href={tag.edit_url}
                                    className="shrink-0 transition-opacity hover:opacity-80"
                                >
                                    <TagPreview tag={tag} />
                                </Link>
                                <Link
                                    href={tag.edit_url}
                                    className="flex min-w-0 flex-1 flex-col space-y-1.5 hover:opacity-80"
                                >
                                    <div className="flex items-start gap-2">
                                        <span className="line-clamp-2 font-semibold break-words text-foreground">
                                            {tag.title}
                                        </span>
                                    </div>
                                    <TagMeta tag={tag} />
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
                                                (tag.status === 'published'
                                                    ? 'success'
                                                    : tag.status === 'draft'
                                                      ? 'warning'
                                                      : tag.status === 'trash'
                                                        ? 'destructive'
                                                        : 'secondary') as Parameters<
                                                    typeof Badge
                                                >[0]['variant']
                                            }
                                        >
                                            {tag.status_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {getTagDateLabel()}
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {tag.display_date}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    empty={{
                        icon: <TagIcon />,
                        title: 'No tags found',
                        description:
                            'Try a different filter or create the first tag.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
