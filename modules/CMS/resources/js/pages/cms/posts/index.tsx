import { Link, router, usePage } from '@inertiajs/react';
import {
    CopyIcon,
    EyeIcon,
    ExternalLinkIcon,
    FileTextIcon,
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
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { mapStatusTab } from '../../../lib/helpers';
import type { PostIndexPageProps, PostListItem } from '../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'CMS', href: route('cms.posts.index') },
    { title: 'Posts', href: route('cms.posts.index') },
];

const STATUS_BADGE_VARIANT: Record<string, string> = {
    published: 'success',
    draft: 'warning',
    scheduled: 'info',
    pending_review: 'warning',
    trash: 'destructive',
};

function statusBadgeVariant(status: string): string {
    return STATUS_BADGE_VARIANT[status] ?? 'secondary';
}

function getPostPath(post: PostListItem): string | null {
    if (!post.permalink_url) {
        return null;
    }

    try {
        const url = new URL(post.permalink_url, window.location.origin);

        return url.pathname === '/' ? '/' : url.pathname.replace(/\/$/, '');
    } catch {
        return post.permalink_url;
    }
}

function getPostDateLabel(post: PostListItem): string {
    return post.status === 'published' && post.published_at_formatted
        ? 'Published'
        : 'Last Modified';
}

function PostPreview({ post }: { post: PostListItem }) {
    if (post.featured_image_url) {
        return (
            <div className="flex h-12 w-18 shrink-0 overflow-hidden rounded-md border bg-muted">
                <img
                    src={post.featured_image_url}
                    alt={post.title}
                    className="h-full w-full object-cover"
                    loading="lazy"
                />
            </div>
        );
    }

    return (
        <div className="flex h-12 w-18 shrink-0 items-center justify-center rounded-md border bg-muted/50 text-muted-foreground">
            <ImageIcon className="size-4" />
        </div>
    );
}

export default function PostsIndex({
    config,
    rows,
    filters,
    statistics,
}: PostIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddPosts = page.props.auth.abilities.addPosts;
    const canEditPosts = page.props.auth.abilities.editPosts;
    const canDeletePosts = page.props.auth.abilities.deletePosts;
    const canRestorePosts = page.props.auth.abilities.restorePosts;

    const handleBulkAction = (
        action: string,
        selectedPosts: PostListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedPosts.length === 0) return;
        router.post(
            route('cms.posts.bulk-action'),
            {
                action,
                ids: selectedPosts.map((post) => post.id),
                status: filters.status,
            },
            {
                preserveScroll: true,
                onSuccess: () => clearSelection(),
            },
        );
    };

    // ----- Filters -----

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search posts...',
            className: 'lg:min-w-80',
        },
    ];

    // ----- Status Tabs -----

    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) =>
        mapStatusTab(tab, statistics, filters.status),
    );

    // ----- Columns -----

    const columns: DatagridColumn<PostListItem>[] = [
        {
            key: 'title',
            header: 'Title',
            sortable: true,
            cell: (post) => (
                <Link
                    href={post.edit_url}
                    className="flex min-w-0 items-start gap-3 hover:opacity-80"
                >
                    <PostPreview post={post} />

                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="truncate font-medium text-foreground">
                                {post.title}
                            </span>
                            {post.is_featured ? (
                                <Badge variant="outline" className="shrink-0">
                                    Featured
                                </Badge>
                            ) : null}
                        </div>

                        <div className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                            <span className="truncate">by {post.author_name}</span>
                            {getPostPath(post) ? (
                                <>
                                    <span className="text-muted-foreground/50">|</span>
                                    <span className="flex min-w-0 items-center gap-1 truncate">
                                        <ExternalLinkIcon className="size-3 shrink-0" />
                                        <span className="truncate">{getPostPath(post)}</span>
                                    </span>
                                </>
                            ) : null}
                        </div>
                    </div>
                </Link>
            ),
        },
        {
            key: 'categories',
            header: 'Categories',
            cellClassName: 'w-44',
            headerClassName: 'w-44',
            cell: (post) => (
                <div className="flex flex-wrap gap-1.5">
                    {post.categories.length > 0 ? (
                        post.categories.slice(0, 2).map((category) => (
                            <Badge key={category.id} variant="secondary" className="max-w-full truncate">
                                {category.title}
                            </Badge>
                        ))
                    ) : (
                        <span className="text-sm text-muted-foreground">—</span>
                    )}
                    {post.categories.length > 2 ? (
                        <Badge variant="outline">+{post.categories.length - 2}</Badge>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-32 text-center',
            cellClassName: 'w-32 text-center',
            sortable: true,
            sortKey: 'status',
            cell: (post) => (
                <Badge
                    variant={
                        statusBadgeVariant(post.status) as Parameters<typeof Badge>[0]['variant']
                    }
                >
                    {post.status_label}
                </Badge>
            ),
        },
        {
            key: 'display_date',
            header: 'Date',
            headerClassName: 'w-44',
            cellClassName: 'w-44',
            sortable: true,
            sortKey: 'published_at',
            cell: (post) => (
                <div className="space-y-0.5 text-sm">
                    <div className="text-xs text-muted-foreground">{getPostDateLabel(post)}</div>
                    <div className="font-medium text-foreground">{post.display_date}</div>
                </div>
            ),
        },
    ];

    // ----- Row Actions -----

    const rowActions = (post: PostListItem): DatagridAction[] => {
        if (post.is_trashed) {
            return [
                ...(canRestorePosts
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route('cms.posts.restore', post.id),
                              method: 'PATCH' as const,
                              confirm: `Restore "${post.title}"?`,
                          },
                      ]
                    : []),
                ...(canDeletePosts
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route('cms.posts.force-delete', post.id),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${post.title}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }

        return [
            ...(post.permalink_url
                ? [
                      {
                          label: 'View',
                          href: post.permalink_url,
                          icon: <EyeIcon />,
                      },
                  ]
                : []),
            ...(canEditPosts
                ? [
                      {
                          label: 'Edit',
                          href: post.edit_url,
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canAddPosts
                ? [
                      {
                          label: 'Duplicate',
                          icon: <CopyIcon />,
                          href: route('cms.posts.duplicate', post.id),
                          method: 'POST' as const,
                          confirm: 'Create a copy of this post as a draft?',
                      },
                  ]
                : []),
            ...(canDeletePosts
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('cms.posts.destroy', post.id),
                          method: 'DELETE' as const,
                          confirm: `Move "${post.title}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    // ----- Bulk Actions -----

    const bulkActions: DatagridBulkAction<PostListItem>[] = [
        ...(canDeletePosts
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected posts to trash?',
                      onSelect: (items: PostListItem[], clear: () => void) =>
                          handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestorePosts
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected posts from trash?',
                      onSelect: (items: PostListItem[], clear: () => void) =>
                          handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDeletePosts
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected posts? This cannot be undone!',
                      onSelect: (items: PostListItem[], clear: () => void) =>
                          handleBulkAction('force_delete', items, clear),
                  },
              ]
            : []),
    ];

    const visibleBulkActions =
        filters.status === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Posts"
            description="Manage blog posts and articles"
            headerActions={
                canAddPosts ? (
                    <Button asChild>
                        <Link href={route('cms.posts.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Create
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.posts.index')}
                    rows={rows}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(post) => post.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={{
                        sort: filters.sort,
                        direction: filters.direction,
                    }}
                    perPage={{
                        value: filters.per_page,
                        options: [10, 25, 50, 100],
                    }}
                    view={{
                        value: (filters.view as 'table' | 'cards') ?? 'table',
                        storageKey: 'cms-posts-datagrid-view',
                    }}
                    renderCard={(post) => (
                        <div className="flex flex-col gap-3">
                            <Link
                                href={post.edit_url}
                                className="flex items-start gap-3 hover:opacity-80"
                            >
                                <PostPreview post={post} />

                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="truncate font-medium text-foreground">
                                            {post.title}
                                        </span>
                                        {post.is_featured ? (
                                            <Badge variant="outline">Featured</Badge>
                                        ) : null}
                                    </div>
                                    <div className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                                        <span className="truncate">by {post.author_name}</span>
                                        {getPostPath(post) ? (
                                            <>
                                                <span className="text-muted-foreground/50">|</span>
                                                <span className="flex min-w-0 items-center gap-1 truncate">
                                                    <ExternalLinkIcon className="size-3 shrink-0" />
                                                    <span className="truncate">{getPostPath(post)}</span>
                                                </span>
                                            </>
                                        ) : null}
                                    </div>
                                </div>
                            </Link>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Categories
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {post.categories.length > 0 ? (
                                            post.categories.slice(0, 2).map((category) => (
                                                <Badge key={category.id} variant="secondary">
                                                    {category.title}
                                                </Badge>
                                            ))
                                        ) : (
                                            <span className="text-sm text-muted-foreground">—</span>
                                        )}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Status
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                statusBadgeVariant(
                                                    post.status,
                                                ) as Parameters<
                                                    typeof Badge
                                                >[0]['variant']
                                            }
                                        >
                                            {post.status_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {getPostDateLabel(post)}
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {post.display_date}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    empty={{
                        icon: <FileTextIcon />,
                        title: 'No posts found',
                        description:
                            'Try a different filter or create the first post.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
