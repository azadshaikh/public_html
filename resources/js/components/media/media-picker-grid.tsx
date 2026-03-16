import { router } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn, DatagridFilter, DatagridTab } from '@/components/datagrid/datagrid';
import { ImageIcon, ListIcon, Trash2Icon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { MediaListItem, MediaPickerFilters } from '@/types/media';
import type { PaginatedData } from '@/types/pagination';
import { getFileExtension, getFileTypeIcon, isImageMime } from './media-picker-utils';

type MediaPickerGridProps = {
    pickerMedia: PaginatedData<MediaListItem> | null;
    pickerFilters: MediaPickerFilters | null;
    pickerAction: string;
    activeMediaId: number | null;
    onMediaClick: (item: MediaListItem) => void;
};

const emptyPaginatedData: PaginatedData<MediaListItem> = {
    data: [],
    current_page: 1,
    from: null,
    last_page: 1,
    links: [],
    next_page_url: null,
    path: '',
    per_page: 24,
    prev_page_url: null,
    to: null,
    total: 0,
};

export function MediaPickerGrid({
    pickerMedia,
    pickerFilters,
    pickerAction,
    activeMediaId,
    onMediaClick,
}: MediaPickerGridProps) {
    const gridColumns: DatagridColumn<MediaListItem>[] = useMemo(
        () => [
            {
                key: 'file_name',
                header: 'File Name',
                sortable: true,
                cell: (item: MediaListItem) => (
                    <button
                        type="button"
                        onClick={() => onMediaClick(item)}
                        className="flex min-w-0 items-center gap-3 text-left hover:opacity-80"
                    >
                        <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded bg-muted">
                            {isImageMime(item.mime_type) && item.thumbnail_url ? (
                                <img
                                    src={item.thumbnail_url}
                                    alt={item.alt_text || item.name}
                                    className="size-10 object-cover"
                                />
                            ) : (
                                getFileTypeIcon(item.mime_type, 'size-5 text-muted-foreground')
                            )}
                        </div>
                        <div className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-medium">
                                {item.file_name}
                            </span>
                            {item.name !== item.file_name && (
                                <span className="block truncate text-xs text-muted-foreground">
                                    {item.name}
                                </span>
                            )}
                        </div>
                    </button>
                ),
            },
            {
                key: 'mime_type',
                header: 'Type',
                headerClassName: 'w-28',
                cellClassName: 'w-28',
                cell: (item: MediaListItem) => (
                    <span className="text-sm text-muted-foreground">
                        {item.mime_type_label}
                    </span>
                ),
            },
            {
                key: 'human_readable_size',
                header: 'Size',
                headerClassName: 'w-24',
                cellClassName: 'w-24',
                sortable: true,
                sortKey: 'size',
            },
            {
                key: 'created_at',
                header: 'Uploaded',
                headerClassName: 'w-32',
                cellClassName: 'w-32',
                type: 'date',
                sortable: true,
            },
        ],
        [onMediaClick],
    );

    const gridFilters: DatagridFilter[] = useMemo(
        () => [
            {
                type: 'search' as const,
                name: 'search',
                value: pickerFilters?.search ?? '',
                placeholder: 'Search files…',
                className: 'lg:min-w-60',
            },
            {
                type: 'select' as const,
                name: 'mime_type_category',
                value: pickerFilters?.mime_type_category ?? '',
                options: [
                    { value: '', label: 'All Types' },
                    { value: 'image', label: 'Images' },
                    { value: 'video', label: 'Videos' },
                    { value: 'audio', label: 'Audio' },
                    { value: 'document', label: 'Documents' },
                ],
            },
            {
                type: 'hidden' as const,
                name: 'picker',
                value: '1',
            },
        ],
        [pickerFilters],
    );

    const gridTabs: DatagridTab[] = useMemo(
        () => [
            {
                label: 'All',
                value: 'all',
                active: (pickerFilters?.status ?? 'all') === 'all',
                icon: <ListIcon />,
            },
            {
                label: 'Trash',
                value: 'trash',
                active: pickerFilters?.status === 'trash',
                icon: <Trash2Icon />,
            },
        ],
        [pickerFilters?.status],
    );

    return (
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
            {/* Datagrid */}
            <div className="flex-1 overflow-hidden p-2">
                <Datagrid
                    action={pickerAction}
                    rows={pickerMedia ?? emptyPaginatedData}
                    columns={gridColumns}
                    filters={gridFilters}
                    tabs={{
                        name: 'status',
                        items: gridTabs,
                    }}
                    getRowKey={(item) => item.id}
                    view={{
                        value: pickerFilters?.view ?? 'cards',
                        storageKey: 'media-picker-view',
                    }}
                    cardGridClassName="grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-3 p-3"
                    renderCard={(item) => {
                        const isActive = activeMediaId === item.id;
                        return (
                            <button
                                type="button"
                                onClick={() => onMediaClick(item)}
                                className={cn(
                                    'group relative aspect-square w-full overflow-hidden bg-muted',
                                    isActive && 'ring-2 ring-primary ring-offset-2',
                                )}
                            >
                                {/* Thumbnail or file icon */}
                                {isImageMime(item.mime_type) && item.thumbnail_url ? (
                                    <img
                                        src={item.thumbnail_url}
                                        alt={item.alt_text || item.name}
                                        className="size-full object-cover transition-transform duration-200 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center">
                                        {getFileTypeIcon(
                                            item.mime_type,
                                            'size-10 text-muted-foreground/40',
                                        )}
                                    </div>
                                )}

                                {/* Extension badge */}
                                <div className="absolute bottom-0 left-0">
                                    <span className="inline-block rounded-tr-md bg-black/60 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">
                                        {getFileExtension(item.file_name)}
                                    </span>
                                </div>
                            </button>
                        );
                    }}
                    sorting={{
                        sort: pickerFilters?.sort ?? 'created_at',
                        direction: pickerFilters?.direction ?? 'desc',
                    }}
                    perPage={{
                        value: pickerFilters?.per_page ?? 24,
                        options: [24, 48, 96],
                    }}
                    empty={{
                        icon: <ImageIcon />,
                        title: 'No media files found',
                        description: 'Upload files to get started.',
                    }}
                />
            </div>
        </div>
    );
}
