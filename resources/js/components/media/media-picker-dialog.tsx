'use client';

import { router } from '@inertiajs/react';
import {
    CheckIcon,
    FileAudioIcon,
    FileIcon,
    FileSpreadsheetIcon,
    FileTextIcon,
    FileVideoIcon,
    ImageIcon,
    Loader2Icon,
    UploadCloudIcon,
    XIcon,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn, DatagridFilter } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type { MediaListItem, MediaPickerFilters, UploadSettings } from '@/types/media';
import type { PaginatedData } from '@/types/pagination';

// ─── Types ───────────────────────────────────────────────────────────────

/**
 * Lighter type returned by onSelect / used by MediaPickerField.
 * A subset of MediaListItem.
 */
export type MediaPickerItem = {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    human_readable_size: string;
    thumbnail_url: string | null;
    original_url: string | null;
    media_url: string | null;
    alt_text: string;
    created_at: string;
};

type MediaPickerSelection = 'single' | 'multiple';



type MediaPickerDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (items: MediaPickerItem[]) => void;
    selection?: MediaPickerSelection;
    maxSelections?: number;
    title?: string;
    defaultTab?: 'upload' | 'library';
    /** Inertia-backed paginated media data (null on first load). */
    pickerMedia: PaginatedData<MediaListItem> | null;
    /** Current filter state from the server (null on first load). */
    pickerFilters: MediaPickerFilters | null;
    /** Upload settings from the server. */
    uploadSettings: UploadSettings | null;
    /** The Inertia action URL the Datagrid submits to (current page URL). */
    pickerAction: string;
};

type UploadingFile = {
    id: string;
    file: File;
    name: string;
    size: number;
    progress: number;
    status: 'pending' | 'uploading' | 'success' | 'error';
    error?: string;
    previewUrl?: string;
    result?: MediaPickerItem;
};

// ─── Helpers ─────────────────────────────────────────────────────────────

function formatSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

function isImageMime(mime: string): boolean {
    return mime.startsWith('image/');
}

function getFileExtension(fileName: string): string {
    const ext = fileName.split('.').pop();
    return ext ? ext.toUpperCase() : '?';
}

function getFileTypeIcon(mimeType: string, className: string) {
    if (mimeType.startsWith('video/')) {
        return <FileVideoIcon className={className} />;
    }
    if (mimeType.startsWith('audio/')) {
        return <FileAudioIcon className={className} />;
    }
    if (
        mimeType === 'application/pdf' ||
        mimeType.includes('word') ||
        mimeType.includes('document') ||
        mimeType === 'text/plain'
    ) {
        return <FileTextIcon className={className} />;
    }
    if (mimeType.includes('spreadsheet') || mimeType.includes('excel') || mimeType === 'text/csv') {
        return <FileSpreadsheetIcon className={className} />;
    }
    return <FileIcon className={className} />;
}

function toPickerItem(item: MediaListItem): MediaPickerItem {
    return {
        id: item.id,
        name: item.name,
        file_name: item.file_name,
        mime_type: item.mime_type,
        size: item.size,
        human_readable_size: item.human_readable_size,
        thumbnail_url: item.thumbnail_url,
        original_url: item.original_url,
        media_url: item.media_url,
        alt_text: item.alt_text,
        created_at: item.created_at,
    };
}

// ─── Constants ───────────────────────────────────────────────────────────

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

// ─── Component ───────────────────────────────────────────────────────────

export function MediaPickerDialog({
    open,
    onOpenChange,
    onSelect,
    selection = 'single',
    maxSelections = 1,
    title = 'Select Media',
    defaultTab = 'library',
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerAction,
}: MediaPickerDialogProps) {
    const [tab, setTab] = useState<'upload' | 'library'>(defaultTab);
    const [selected, setSelected] = useState<Map<number, MediaPickerItem>>(new Map());
    const initialLoadTriggeredRef = useRef(false);

    // Upload state
    const [uploadFiles, setUploadFiles] = useState<UploadingFile[]>([]);
    const [isDragOver, setIsDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const dragCounterRef = useRef(0);
    const uploadedIdsRef = useRef<Set<string>>(new Set());
    const refreshedAfterUploadRef = useRef(false);

    // ── Reset on open/close ──────────────────────────────────────

    useEffect(() => {
        if (open) {
            setSelected(new Map());
            setTab(defaultTab);
            setUploadFiles([]);
            uploadedIdsRef.current.clear();
            refreshedAfterUploadRef.current = false;
        } else {
            initialLoadTriggeredRef.current = false;
        }
    }, [open, defaultTab]);

    // ── Trigger initial load ─────────────────────────────────────

    useEffect(() => {
        if (!open || pickerMedia || initialLoadTriggeredRef.current) {
            return;
        }

        initialLoadTriggeredRef.current = true;

        router.get(
            pickerAction,
            {
                picker: '1',
                sort: 'created_at',
                direction: 'desc',
                per_page: 24,
                view: 'cards',
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, [open, pickerMedia, pickerAction]);

    // ── Selection logic ──────────────────────────────────────────

    const toggleSelect = useCallback(
        (item: MediaListItem) => {
            setSelected((prev) => {
                const next = new Map(prev);
                if (next.has(item.id)) {
                    next.delete(item.id);
                } else {
                    if (selection === 'single') {
                        next.clear();
                    }
                    if (
                        selection === 'multiple' &&
                        maxSelections > 0 &&
                        next.size >= maxSelections
                    ) {
                        return prev;
                    }
                    next.set(item.id, toPickerItem(item));
                }
                return next;
            });
        },
        [selection, maxSelections],
    );

    const handleConfirm = useCallback(() => {
        const selectedItems = Array.from(selected.values());
        if (selectedItems.length > 0) {
            onSelect(selectedItems);
            onOpenChange(false);
        }
    }, [selected, onSelect, onOpenChange]);

    // ── Upload logic ─────────────────────────────────────────────

    const validateFile = useCallback(
        (file: File): string | null => {
            if (!uploadSettings) return 'Upload settings not loaded';
            if (file.size > uploadSettings.max_size_bytes) {
                return `File exceeds ${uploadSettings.max_size_mb}MB limit`;
            }
            const accepted = uploadSettings.accepted_mime_types
                .split(',')
                .map((t) => t.trim());
            const isAccepted = accepted.some((pattern) => {
                if (pattern.endsWith('/*')) {
                    return file.type.startsWith(pattern.replace('/*', '/'));
                }
                return file.type === pattern;
            });
            if (!isAccepted) {
                return `File type "${file.type}" is not allowed`;
            }
            return null;
        },
        [uploadSettings],
    );

    const stageFiles = useCallback(
        (fileList: FileList | File[]) => {
            const newFiles: UploadingFile[] = Array.from(fileList).map((file) => {
                const error = validateFile(file);
                return {
                    id: crypto.randomUUID(),
                    file,
                    name: file.name,
                    size: file.size,
                    progress: 0,
                    status: error ? 'error' : 'pending',
                    error: error ?? undefined,
                    previewUrl: file.type.startsWith('image/')
                        ? URL.createObjectURL(file)
                        : undefined,
                };
            });

            setUploadFiles((prev) => [...prev, ...newFiles]);
        },
        [validateFile],
    );

    const uploadSingleFile = useCallback(
        (uf: UploadingFile) => {
            if (!uploadSettings) return;

            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('file', uf.file);

            const csrfMeta = document.head.querySelector(
                'meta[name="csrf-token"]',
            );
            const csrfToken = csrfMeta?.getAttribute('content') ?? '';

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const progress = Math.round((e.loaded / e.total) * 100);
                    setUploadFiles((prev) =>
                        prev.map((f) =>
                            f.id === uf.id
                                ? { ...f, progress, status: 'uploading' }
                                : f,
                        ),
                    );
                }
            };

            xhr.onload = () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (
                        xhr.status >= 200 &&
                        xhr.status < 300 &&
                        response.status === 1
                    ) {
                        const fileData = response.file;
                        const resultItem: MediaPickerItem = {
                            id: fileData.id,
                            name: fileData.name,
                            file_name: fileData.file_name,
                            mime_type: fileData.type,
                            size: fileData.size,
                            human_readable_size: formatSize(fileData.size),
                            thumbnail_url: fileData.thumb ?? fileData.url,
                            original_url: fileData.url,
                            media_url: fileData.url,
                            alt_text: fileData.alt_text ?? '',
                            created_at: new Date().toISOString(),
                        };

                        setUploadFiles((prev) =>
                            prev.map((f) =>
                                f.id === uf.id
                                    ? {
                                          ...f,
                                          progress: 100,
                                          status: 'success',
                                          result: resultItem,
                                      }
                                    : f,
                            ),
                        );
                    } else {
                        setUploadFiles((prev) =>
                            prev.map((f) =>
                                f.id === uf.id
                                    ? {
                                          ...f,
                                          status: 'error',
                                          error: response.error || 'Upload failed',
                                      }
                                    : f,
                            ),
                        );
                    }
                } catch {
                    setUploadFiles((prev) =>
                        prev.map((f) =>
                            f.id === uf.id
                                ? {
                                      ...f,
                                      status: 'error',
                                      error: 'Invalid server response',
                                  }
                                : f,
                        ),
                    );
                }
            };

            xhr.onerror = () => {
                setUploadFiles((prev) =>
                    prev.map((f) =>
                        f.id === uf.id
                            ? { ...f, status: 'error', error: 'Network error' }
                            : f,
                    ),
                );
            };

            xhr.open('POST', uploadSettings.upload_route);
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(formData);
        },
        [uploadSettings],
    );

    // Fire uploads for pending files
    useEffect(() => {
        const pending = uploadFiles.filter(
            (f) =>
                f.status === 'pending' &&
                !f.error &&
                !uploadedIdsRef.current.has(f.id),
        );
        pending.forEach((f) => {
            uploadedIdsRef.current.add(f.id);
            uploadSingleFile(f);
        });
    }, [uploadFiles, uploadSingleFile]);

    // Auto-select uploaded files and refresh library
    const successfulUploads = useMemo(
        () => uploadFiles.filter((f) => f.status === 'success' && f.result),
        [uploadFiles],
    );

    useEffect(() => {
        if (successfulUploads.length === 0) {
            refreshedAfterUploadRef.current = false;
            return;
        }

        const allDone = uploadFiles.every(
            (f) => f.status === 'success' || f.status === 'error',
        );
        if (!allDone || refreshedAfterUploadRef.current) return;

        refreshedAfterUploadRef.current = true;

        // Auto-select newly uploaded items
        setSelected((prev) => {
            const next = new Map(prev);
            for (const uf of successfulUploads) {
                if (!uf.result) continue;
                if (selection === 'single') {
                    next.clear();
                    next.set(uf.result.id, uf.result);
                    break;
                }
                if (maxSelections > 0 && next.size >= maxSelections) break;
                next.set(uf.result.id, uf.result);
            }
            return next;
        });

        // Refresh library data so new uploads appear in the grid
        router.get(
            pickerAction,
            {
                picker: '1',
                sort: 'created_at',
                direction: 'desc',
                per_page: pickerFilters?.per_page ?? 24,
                view: pickerFilters?.view ?? 'cards',
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, [successfulUploads, uploadFiles, selection, maxSelections, pickerAction, pickerFilters]);

    // Drag events
    const handleDragEnter = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounterRef.current++;
        setIsDragOver(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounterRef.current--;
        if (dragCounterRef.current === 0) {
            setIsDragOver(false);
        }
    }, []);

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            dragCounterRef.current = 0;
            setIsDragOver(false);
            if (e.dataTransfer.files.length > 0) {
                stageFiles(e.dataTransfer.files);
            }
        },
        [stageFiles],
    );

    const removeUploadFile = useCallback((id: string) => {
        setUploadFiles((prev) => {
            const file = prev.find((f) => f.id === id);
            if (file?.previewUrl) {
                URL.revokeObjectURL(file.previewUrl);
            }
            return prev.filter((f) => f.id !== id);
        });
    }, []);

    // ── Derived state ────────────────────────────────────────────

    const hasActiveUploads = uploadFiles.some(
        (f) => f.status === 'pending' || f.status === 'uploading',
    );

    const selectedPreview = useMemo(() => {
        if (selected.size === 0) return null;
        return Array.from(selected.values())[0];
    }, [selected]);

    const isLoading = open && !pickerMedia;

    // ── Datagrid configuration ───────────────────────────────────

    const gridColumns: DatagridColumn<MediaListItem>[] = useMemo(
        () => [
            {
                key: 'file_name',
                header: 'File Name',
                sortable: true,
                cell: (item: MediaListItem) => {
                    const isItemSelected = selected.has(item.id);
                    return (
                        <button
                            type="button"
                            onClick={() => toggleSelect(item)}
                            className="flex min-w-0 items-center gap-3 text-left hover:opacity-80"
                        >
                            <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded bg-muted">
                                {item.thumbnail_url ? (
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
                                <span className={cn(
                                    'block truncate text-sm font-medium',
                                    isItemSelected && 'text-primary',
                                )}>
                                    {item.file_name}
                                </span>
                                {item.name !== item.file_name && (
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {item.name}
                                    </span>
                                )}
                            </div>
                            {isItemSelected && (
                                <div className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                    <CheckIcon className="size-3 stroke-[3]" />
                                </div>
                            )}
                        </button>
                    );
                },
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
        [selected, toggleSelect],
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

    // ── Render ───────────────────────────────────────────────────

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[85vh] flex-col sm:max-w-5xl" showCloseButton>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ImageIcon className="size-5 text-primary" />
                        {title}
                    </DialogTitle>
                    <DialogDescription>
                        {selection === 'single'
                            ? 'Choose a file from your library or upload a new one.'
                            : `Select up to ${maxSelections > 0 ? maxSelections : '∞'} files.`}
                    </DialogDescription>
                </DialogHeader>

                <Tabs
                    value={tab}
                    onValueChange={(v) => setTab(v as 'upload' | 'library')}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <TabsList>
                        <TabsTrigger value="library">
                            <ImageIcon data-icon="inline-start" />
                            Library
                        </TabsTrigger>
                        <TabsTrigger value="upload">
                            <UploadCloudIcon data-icon="inline-start" />
                            Upload
                        </TabsTrigger>
                    </TabsList>

                    {/* ── Library tab ──────────────────────────── */}
                    <TabsContent
                        value="library"
                        className="flex min-h-0 flex-1 flex-col overflow-auto"
                    >
                        {isLoading ? (
                            <div className="flex flex-1 items-center justify-center py-16">
                                <div className="flex flex-col items-center gap-3 text-muted-foreground">
                                    <Loader2Icon className="size-8 animate-spin" />
                                    <span className="text-sm">Loading media…</span>
                                </div>
                            </div>
                        ) : (
                            <Datagrid
                                action={pickerAction}
                                rows={pickerMedia ?? emptyPaginatedData}
                                columns={gridColumns}
                                filters={gridFilters}
                                getRowKey={(item) => item.id}
                                view={{
                                    value: pickerFilters?.view ?? 'cards',
                                    storageKey: 'media-picker-view',
                                }}
                                cardGridClassName="grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 p-3"
                                renderCard={(item) => {
                                    const isItemSelected = selected.has(item.id);
                                    return (
                                        <button
                                            type="button"
                                            onClick={() => toggleSelect(item)}
                                            className={cn(
                                                'group relative aspect-square w-full overflow-hidden',
                                                isItemSelected && 'ring-2 ring-primary ring-offset-2',
                                            )}
                                        >
                                            {/* Thumbnail or file icon */}
                                            {item.thumbnail_url && isImageMime(item.mime_type) ? (
                                                <img
                                                    src={item.thumbnail_url}
                                                    alt={item.alt_text || item.name}
                                                    className="size-full object-cover transition-transform duration-200 group-hover:scale-105"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center bg-muted">
                                                    {getFileTypeIcon(item.mime_type, 'size-10 text-muted-foreground/40')}
                                                </div>
                                            )}

                                            {/* Selection overlay */}
                                            {isItemSelected && (
                                                <div className="absolute inset-0 bg-primary/10" />
                                            )}

                                            {/* Check badge */}
                                            {isItemSelected && (
                                                <div className="absolute top-1.5 right-1.5 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm">
                                                    <CheckIcon className="size-3 stroke-[3]" />
                                                </div>
                                            )}

                                            {/* Extension badge */}
                                            <div className="absolute bottom-0 left-0">
                                                <span className="inline-block rounded-tr-md bg-black/60 px-1.5 py-0.5 text-[10px] font-bold text-white uppercase">
                                                    {getFileExtension(item.file_name)}
                                                </span>
                                            </div>

                                            {/* Hover details */}
                                            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-1.5 opacity-0 transition-opacity group-hover:opacity-100">
                                                <p className="truncate text-[10px] font-medium text-white">
                                                    {item.name}
                                                </p>
                                                <p className="text-[9px] text-white/70">
                                                    {item.human_readable_size}
                                                </p>
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
                        )}
                    </TabsContent>

                    {/* ── Upload tab ───────────────────────────── */}
                    <TabsContent
                        value="upload"
                        className="flex min-h-0 flex-1 flex-col gap-3"
                    >
                        {/* Dropzone */}
                        <div
                            onDragEnter={handleDragEnter}
                            onDragLeave={handleDragLeave}
                            onDragOver={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                            }}
                            onDrop={handleDrop}
                            onClick={() => fileInputRef.current?.click()}
                            className={cn(
                                'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-10 transition-colors',
                                isDragOver
                                    ? 'border-primary bg-primary/5'
                                    : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50',
                            )}
                        >
                            <UploadCloudIcon
                                className={cn(
                                    'mb-3 size-10',
                                    isDragOver
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            />
                            <p className="text-sm font-medium text-foreground">
                                Drop files here or click to browse
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {uploadSettings
                                    ? `${uploadSettings.friendly_file_types} — Max ${uploadSettings.max_size_mb}MB per file`
                                    : 'Loading settings…'}
                            </p>
                            <input
                                ref={fileInputRef}
                                type="file"
                                multiple={selection === 'multiple'}
                                accept={
                                    uploadSettings?.accepted_mime_types ?? ''
                                }
                                onChange={(e) => {
                                    if (e.target.files) {
                                        stageFiles(e.target.files);
                                        e.target.value = '';
                                    }
                                }}
                                className="hidden"
                            />
                        </div>

                        {/* Upload file list */}
                        {uploadFiles.length > 0 && (
                            <ScrollArea className="min-h-0 flex-1">
                                <div className="space-y-2">
                                    {uploadFiles.map((uf) => (
                                        <div
                                            key={uf.id}
                                            className={cn(
                                                'flex items-center gap-3 rounded-lg border p-2.5',
                                                uf.status === 'success' &&
                                                    'border-green-200 bg-green-50/50 dark:border-green-900 dark:bg-green-950/30',
                                                uf.status === 'error' &&
                                                    'border-destructive/30 bg-destructive/5',
                                            )}
                                        >
                                            {/* Preview */}
                                            <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded bg-muted">
                                                {uf.previewUrl ? (
                                                    <img
                                                        src={uf.previewUrl}
                                                        alt={uf.name}
                                                        className="size-10 object-cover"
                                                    />
                                                ) : (
                                                    <FileIcon className="size-4 text-muted-foreground" />
                                                )}
                                            </div>

                                            {/* Info */}
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate text-sm font-medium">
                                                        {uf.name}
                                                    </span>
                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                        {formatSize(uf.size)}
                                                    </span>
                                                </div>
                                                {uf.status === 'uploading' && (
                                                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full rounded-full bg-primary transition-all"
                                                            style={{
                                                                width: `${uf.progress}%`,
                                                            }}
                                                        />
                                                    </div>
                                                )}
                                                {uf.status === 'error' && (
                                                    <p className="mt-0.5 text-xs text-destructive">
                                                        {uf.error}
                                                    </p>
                                                )}
                                            </div>

                                            {/* Status / actions */}
                                            <div className="flex shrink-0 items-center gap-1">
                                                {(uf.status === 'pending' ||
                                                    uf.status ===
                                                        'uploading') && (
                                                    <Loader2Icon className="size-4 animate-spin text-primary" />
                                                )}
                                                {uf.status === 'success' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-green-300 text-green-700 dark:border-green-700 dark:text-green-400"
                                                    >
                                                        <CheckIcon data-icon="inline-start" />
                                                        Done
                                                    </Badge>
                                                )}
                                                {uf.status === 'error' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            removeUploadFile(
                                                                uf.id,
                                                            );
                                                        }}
                                                    >
                                                        <XIcon />
                                                        <span className="sr-only">
                                                            Remove
                                                        </span>
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </ScrollArea>
                        )}
                    </TabsContent>
                </Tabs>

                {/* ── Footer with selection info + confirm ─── */}
                <DialogFooter>
                    {selected.size > 0 && selectedPreview && (
                        <div className="mr-auto flex items-center gap-2">
                            {isImageMime(selectedPreview.mime_type) &&
                                selectedPreview.thumbnail_url && (
                                    <img
                                        src={selectedPreview.thumbnail_url}
                                        alt=""
                                        className="size-8 rounded border object-cover"
                                    />
                                )}
                            <span className="text-sm text-muted-foreground">
                                {selected.size === 1
                                    ? selectedPreview.name
                                    : `${selected.size} files selected`}
                            </span>
                        </div>
                    )}
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleConfirm}
                        disabled={selected.size === 0 || hasActiveUploads}
                    >
                        {selection === 'single'
                            ? 'Select'
                            : `Select (${selected.size})`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
