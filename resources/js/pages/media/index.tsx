import { router } from '@inertiajs/react';
import {
    EyeIcon,
    FileIcon,
    ImageIcon,
    ListIcon,
    RefreshCwIcon,
    Trash2Icon,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import {
    index as mediaLibraryIndex,
    bulkAction,
} from '@/actions/App/Http/Controllers/MediaLibraryController';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { MediaDetailSheet } from '@/components/media/media-detail-sheet';
import { MediaUploadDropzone } from '@/components/media/media-upload-dropzone';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import type { MediaIndexPageProps, MediaListItem } from '@/types/media';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Media Library', href: mediaLibraryIndex() },
];

// =========================================================================
// MIME TYPE HELPERS
// =========================================================================

const MIME_BADGE_VARIANT: Record<string, string> = {
    image: 'success',
    video: 'info',
    audio: 'warning',
    application: 'secondary',
    text: 'outline',
};

function getMimeCategory(mimeType: string): string {
    return mimeType.split('/')[0] ?? 'unknown';
}

function getMimeBadgeVariant(mimeType: string): string {
    return MIME_BADGE_VARIANT[getMimeCategory(mimeType)] ?? 'outline';
}

function isImageMimeType(mimeType: string): boolean {
    return mimeType.startsWith('image/');
}

function getFileExtension(fileName: string): string {
    const ext = fileName.split('.').pop();
    return ext ? ext.toUpperCase() : '?';
}

// =========================================================================
// PAGE COMPONENT
// =========================================================================

export default function MediaIndex({
    media,
    filters,
    statistics,
    uploadSettings,
    storageData,
    filterOptions,
    status,
    error,
}: MediaIndexPageProps) {
    const [detailMediaId, setDetailMediaId] = useState<number | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);

    const openDetail = useCallback((id: number) => {
        setDetailMediaId(id);
        setDetailOpen(true);
    }, []);

    // ----- Bulk action helper -----

    const handleBulkAction = useCallback(
        (
            action: string,
            selectedItems: MediaListItem[],
            clearSelection: () => void,
        ) => {
            if (selectedItems.length === 0) return;

            router.post(
                bulkAction().url,
                {
                    action,
                    ids: selectedItems.map((item) => item.id),
                },
                {
                    preserveScroll: true,
                    onSuccess: () => clearSelection(),
                },
            );
        },
        [],
    );

    // Single-item actions via bulkAction endpoint for Inertia redirect consistency
    const handleSingleAction = useCallback(
        (action: string, item: MediaListItem) => {
            router.post(
                bulkAction().url,
                { action, ids: [item.id] },
                { preserveScroll: true },
            );
        },
        [],
    );

    // ----- Filters -----

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search files...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'mime_type_category',
            value: filters.mime_type_category ?? '',
            options: [
                { value: '', label: 'All Types' },
                ...filterOptions.mime_type_category,
            ],
        },
        {
            type: 'select',
            name: 'created_by',
            value: filters.created_by ?? '',
            options: [
                { value: '', label: 'All Users' },
                ...filterOptions.created_by,
            ],
        },
    ];

    // ----- Status tabs -----

    const statusTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: statistics.total,
            active: filters.status === 'all',
            icon: <ListIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Trash',
            value: 'trash',
            count: statistics.trash,
            active: filters.status === 'trash',
            icon: <Trash2Icon />,
            countVariant: 'destructive',
        },
    ];

    // ----- Columns -----

    const columns: DatagridColumn<MediaListItem>[] = [
        {
            key: 'thumbnail',
            header: '',
            headerClassName: 'w-16',
            cellClassName: 'w-16',
            cell: (item) => (
                <button
                    type="button"
                    onClick={() => openDetail(item.id)}
                    className="flex size-10 items-center justify-center overflow-hidden rounded bg-muted hover:ring-2 hover:ring-primary/50"
                >
                    {item.thumbnail_url ? (
                        <img
                            src={item.thumbnail_url}
                            alt={item.alt_text || item.name}
                            className="size-10 object-cover"
                        />
                    ) : (
                        <FileIcon className="size-5 text-muted-foreground" />
                    )}
                </button>
            ),
        },
        {
            key: 'file_name',
            header: 'File Name',
            sortable: true,
            sortKey: 'file_name',
            cell: (item) => (
                <button
                    type="button"
                    onClick={() => openDetail(item.id)}
                    className="flex min-w-0 flex-col gap-0.5 text-left hover:opacity-80"
                >
                    <span className="truncate text-sm font-medium text-foreground">
                        {item.file_name}
                    </span>
                    {item.name !== item.file_name && (
                        <span className="truncate text-xs text-muted-foreground">
                            {item.name}
                        </span>
                    )}
                </button>
            ),
        },
        {
            key: 'mime_type',
            header: 'Type',
            headerClassName: 'w-32',
            cellClassName: 'w-32',
            cell: (item) => (
                <Badge variant={getMimeBadgeVariant(item.mime_type)}>
                    {item.mime_type_label}
                </Badge>
            ),
        },
        {
            key: 'human_readable_size',
            header: 'Size',
            headerClassName: 'w-28',
            cellClassName: 'w-28',
            sortable: true,
            sortKey: 'size',
            cell: (item) => (
                <span className="text-sm text-muted-foreground">
                    {item.human_readable_size}
                </span>
            ),
        },
        {
            key: 'created_at',
            header: 'Uploaded',
            headerClassName: 'w-40',
            cellClassName: 'w-40',
            sortable: true,
            sortKey: 'created_at',
            cell: (item) => (
                <span className="text-xs text-muted-foreground">
                    {new Date(item.created_at).toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                    })}
                </span>
            ),
        },
    ];

    // ----- Row actions -----

    const rowActions = (item: MediaListItem): DatagridAction[] => {
        const actions: DatagridAction[] = [
            {
                label: 'View Details',
                icon: <EyeIcon />,
                onSelect: () => openDetail(item.id),
            },
        ];

        if (item.is_trashed) {
            actions.push({
                label: 'Restore',
                icon: <RefreshCwIcon />,
                onSelect: () => handleSingleAction('restore', item),
                confirm: 'Restore this file?',
            });
            actions.push({
                label: 'Delete Permanently',
                icon: <Trash2Icon />,
                onSelect: () => handleSingleAction('force_delete', item),
                confirm: 'Permanently delete this file? This cannot be undone.',
                variant: 'destructive',
            });
        } else {
            actions.push({
                label: 'Move to Trash',
                icon: <Trash2Icon />,
                onSelect: () => handleSingleAction('delete', item),
                confirm: 'Move this file to trash?',
                variant: 'destructive',
            });
        }

        return actions;
    };

    // ----- Bulk actions -----

    const allBulkActions: DatagridBulkAction<MediaListItem>[] = [
        {
            key: 'bulk-delete',
            label: 'Move to Trash',
            icon: <Trash2Icon />,
            variant: 'destructive',
            confirm: 'Move selected files to trash?',
            onSelect: (rows, clear) => handleBulkAction('delete', rows, clear),
        },
        {
            key: 'bulk-restore',
            label: 'Restore',
            icon: <RefreshCwIcon />,
            confirm: 'Restore selected files?',
            onSelect: (rows, clear) => handleBulkAction('restore', rows, clear),
        },
        {
            key: 'bulk-force-delete',
            label: 'Delete Permanently',
            icon: <Trash2Icon />,
            variant: 'destructive',
            confirm:
                'Permanently delete selected files? This cannot be undone!',
            onSelect: (rows, clear) =>
                handleBulkAction('force_delete', rows, clear),
        },
    ];

    const visibleBulkActions =
        filters.status === 'trash'
            ? allBulkActions.filter((a) => a.key !== 'bulk-delete')
            : allBulkActions.filter((a) => a.key === 'bulk-delete');

    // ----- Render -----

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Media Library"
            description="Manage your media files"
            headerActions={
                <div className="flex items-center gap-2">
                    <Badge
                        variant="outline"
                        className="gap-1.5 px-3 py-1 text-sm"
                    >
                        <span className="text-muted-foreground">Used:</span>{' '}
                        {storageData.used_size_readable}
                    </Badge>
                    <Badge
                        variant="outline"
                        className="gap-1.5 px-3 py-1 text-sm"
                    >
                        <span className="text-muted-foreground">Limit:</span>{' '}
                        {storageData.max_size_readable}
                    </Badge>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<ImageIcon />}
                    error={error}
                    errorIcon={<ImageIcon />}
                />

                {/* Upload Dropzone */}
                <MediaUploadDropzone uploadSettings={uploadSettings} />

                {/* Datagrid */}
                <Datagrid
                    action={mediaLibraryIndex().url}
                    rows={media}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{
                        name: 'status',
                        items: statusTabs,
                    }}
                    getRowKey={(item) => item.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    sorting={{
                        sort: filters.sort,
                        direction: filters.direction,
                    }}
                    perPage={{
                        value: filters.per_page,
                        options: [24, 48, 96],
                    }}
                    view={{
                        value: filters.view,
                        storageKey: 'media-library-datagrid-view',
                    }}
                    cardGridClassName="grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-3 p-3"
                    renderCard={(item) => (
                        <button
                            type="button"
                            onClick={() => openDetail(item.id)}
                            className="group relative aspect-square w-full overflow-hidden bg-muted"
                        >
                            {/* Thumbnail / file icon */}
                            {item.thumbnail_url &&
                            isImageMimeType(item.mime_type) ? (
                                <img
                                    src={item.thumbnail_url}
                                    alt={item.alt_text || item.name}
                                    className="size-full object-cover transition-transform duration-200 group-hover:scale-105"
                                />
                            ) : (
                                <div className="flex size-full items-center justify-center">
                                    <FileIcon className="size-10 text-muted-foreground/40" />
                                </div>
                            )}

                            {/* Optimizing overlay */}
                            {item.is_processing && (
                                <div className="absolute inset-0 flex items-center justify-center bg-background/60">
                                    <Badge
                                        variant="secondary"
                                        className="gap-1 text-xs"
                                    >
                                        <div className="size-3 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                        Optimizing
                                    </Badge>
                                </div>
                            )}

                            {/* Type badge (bottom-left) */}
                            <div className="absolute bottom-0 left-0">
                                <span className="inline-block rounded-tr-md bg-black/60 px-1.5 py-0.5 text-[10px] font-bold text-white uppercase">
                                    {getFileExtension(item.file_name)}
                                </span>
                            </div>

                            {/* Trashed badge */}
                            {item.is_trashed && (
                                <div className="absolute top-1 right-1">
                                    <Badge
                                        variant="destructive"
                                        className="text-[10px] leading-none"
                                    >
                                        Trash
                                    </Badge>
                                </div>
                            )}
                        </button>
                    )}
                    empty={{
                        icon: <ImageIcon />,
                        title: 'No media files found',
                        description:
                            'Drop files above or use the upload area to add media.',
                    }}
                />

                {/* Detail Sheet */}
                <MediaDetailSheet
                    mediaId={detailMediaId}
                    open={detailOpen}
                    onOpenChange={setDetailOpen}
                    onUpdated={() => {
                        router.reload({
                            only: ['media', 'statistics', 'storageData'],
                        });
                    }}
                />
            </div>
        </AppLayout>
    );
}
