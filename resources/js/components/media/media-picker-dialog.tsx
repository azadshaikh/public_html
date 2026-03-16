'use client';

import { router } from '@inertiajs/react';
import { ImageIcon, Loader2Icon, UploadCloudIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { MediaListItem, MediaPickerFilters, UploadSettings } from '@/types/media';
import type { PaginatedData } from '@/types/pagination';
import { MediaPickerDetailsPanel } from './media-picker-details-panel';
import { MediaPickerGrid } from './media-picker-grid';
import { MediaPickerUploadTab } from './media-picker-upload-tab';
import type { MediaPickerItem, MediaPickerSelection } from '@/components/media/media-picker-utils';
import { toPickerItem } from './media-picker-utils';

type MediaPickerDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (items: MediaPickerItem[]) => void;
    selection?: MediaPickerSelection;
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
    /** Statistics for the tabs. */
    pickerStatistics?: {
        total: number;
        trash: number;
    } | null;
    /** The initially selected media ID */
    initialSelectedId?: number | null;
};

type UploadingFile = {
    id: string;
    file: File;
    name: string;
    size: number;
    progress: number;
    status: 'staged' | 'pending' | 'uploading' | 'success' | 'error';
    error?: string;
    previewUrl?: string;
    type: string;
    result?: MediaPickerItem;
};

export function MediaPickerDialog({
    open,
    onOpenChange,
    onSelect,
    selection = 'single',
    title = 'Select Media',
    defaultTab = 'upload',
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerAction,
    pickerStatistics,
    initialSelectedId = null,
}: MediaPickerDialogProps) {
    const [tab, setTab] = useState<'upload' | 'library'>(defaultTab);
    const [activeMediaId, setActiveMediaId] = useState<number | null>(initialSelectedId);
    const [isEditing, setIsEditing] = useState(false);
    const [editedAltText, setEditedAltText] = useState('');
    const [editedTitle, setEditedTitle] = useState('');
    const [editedCaption, setEditedCaption] = useState('');
    const initialLoadTriggeredRef = useRef(false);

    // Upload state
    const [uploadFiles, setUploadFiles] = useState<UploadingFile[]>([]);
    const [isDragOver, setIsDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const dragCounterRef = useRef(0);
    const uploadedIdsRef = useRef<Set<string>>(new Set());
    const refreshedAfterUploadRef = useRef(false);
    const pendingActiveMediaIdRef = useRef<number | null>(null);

    const resetDialogState = useCallback(() => {
        setTab(defaultTab);
        setActiveMediaId(null);
        setUploadFiles((previousFiles) => {
            previousFiles.forEach((file) => {
                if (file.previewUrl) {
                    URL.revokeObjectURL(file.previewUrl);
                }
            });
            return [];
        });
        setIsDragOver(false);
        dragCounterRef.current = 0;
        uploadedIdsRef.current.clear();
        refreshedAfterUploadRef.current = false;
        initialLoadTriggeredRef.current = false;
        setIsEditing(false);
    }, [defaultTab]);

    const handleDialogOpenChange = useCallback(
        (nextOpen: boolean) => {
            if (!nextOpen) {
                resetDialogState();
            } else {
                // When opening, if there is a value prop passed from MediaPickerField,
                // we want to select it by default (this is handled in useEffect below, 
                // but we need to ensure the grid can highlight it).
                if (initialSelectedId) {
                    setActiveMediaId(initialSelectedId);
                }
            }
            onOpenChange(nextOpen);
        },
        [onOpenChange, resetDialogState],
    );

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

    const handleMediaClick = useCallback((item: MediaListItem) => {
        setActiveMediaId(item.id);
        setIsEditing(false);
        setEditedAltText(item.alt_text || '');
        setEditedTitle(item.name || '');
        setEditedCaption(item.caption || '');
    }, []);

    const handleSelectMedia = useCallback(() => {
        if (!activeMediaId) return;
        const activeMedia = pickerMedia?.data.find((m) => m.id === activeMediaId);
        if (!activeMedia) return;

        // Close dialog after selection
        onSelect([toPickerItem(activeMedia)]);
        onOpenChange(false);
    }, [activeMediaId, pickerMedia, onSelect, onOpenChange]);

    const handleSaveMedia = useCallback(() => {
        // TODO: Implement save logic via Inertia
        setIsEditing(false);
    }, []);

    const activeMedia = useMemo(() => {
        if (!activeMediaId || !pickerMedia) return null;
        return pickerMedia.data.find((m) => m.id === activeMediaId) || null;
    }, [activeMediaId, pickerMedia]);

    // ── Upload logic ─────────────────────────────────────────────

    const validateFile = useCallback(
        (file: File): string | null => {
            if (!uploadSettings) return 'Upload settings not loaded';
            if (file.size > uploadSettings.max_size_bytes) {
                return `File exceeds ${uploadSettings.max_size_mb}MB limit`;
            }
            const accepted = uploadSettings.accepted_mime_types.split(',').map((t) => t.trim());
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

    const batchLimitMessage = useCallback((): string => {
        if (!uploadSettings) {
            return 'Upload settings not loaded';
        }
        return `You can upload up to ${uploadSettings.max_files_per_upload} files at once`;
    }, [uploadSettings]);

    const stageFiles = useCallback(
        (fileList: FileList | File[]) => {
            if (!uploadSettings) {
                return;
            }

            const incomingFiles = Array.from(fileList);
            const newFiles: UploadingFile[] = incomingFiles.map((file, index) => {
                const batchError =
                    index >= uploadSettings.max_files_per_upload ? batchLimitMessage() : null;
                const error = validateFile(file);

                return {
                    id: crypto.randomUUID(),
                    file,
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    progress: 0,
                    status: batchError || error ? 'error' : 'staged',
                    error: batchError ?? error ?? undefined,
                    previewUrl: file.type.startsWith('image/')
                        ? URL.createObjectURL(file)
                        : undefined,
                };
            });

            setUploadFiles((prev) => {
                const kept = prev.filter((f) => f.status !== 'success' && f.status !== 'error');
                return [...kept, ...newFiles];
            });
        },
        [uploadSettings, validateFile, batchLimitMessage],
    );

    const uploadSingleFile = useCallback(
        (uf: UploadingFile) => {
            if (!uploadSettings) return;

            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('file', uf.file);

            const csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta?.getAttribute('content');

            setUploadFiles((prev) =>
                prev.map((f) => (f.id === uf.id ? { ...f, status: 'uploading', progress: 0 } : f)),
            );

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const progress = Math.round((e.loaded * 100) / e.total);
                    setUploadFiles((prev) =>
                        prev.map((f) => (f.id === uf.id ? { ...f, progress } : f)),
                    );
                }
            };

            xhr.onload = () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && response.status === 1) {
                        setUploadFiles((prev) =>
                            prev.map((f) =>
                                f.id === uf.id
                                    ? {
                                        ...f,
                                        progress: 100,
                                        status: 'success',
                                        result: response.file,
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
                                ? { ...f, status: 'error', error: 'Invalid response' }
                                : f,
                        ),
                    );
                }
            };

            xhr.onerror = () => {
                setUploadFiles((prev) =>
                    prev.map((f) =>
                        f.id === uf.id ? { ...f, status: 'error', error: 'Network error' } : f,
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
            (f) => f.status === 'pending' && !f.error && !uploadedIdsRef.current.has(f.id),
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

        const allDone = uploadFiles.every((f) => f.status === 'success' || f.status === 'error');
        if (!allDone || refreshedAfterUploadRef.current) return;

        const hasErrors = uploadFiles.some((f) => f.status === 'error');
        refreshedAfterUploadRef.current = true;

        if (successfulUploads.length > 0 && successfulUploads[0].result) {
            pendingActiveMediaIdRef.current = successfulUploads[0].result.id;
        }

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
                onSuccess: () => {
                    if (pendingActiveMediaIdRef.current !== null) {
                        setActiveMediaId(pendingActiveMediaIdRef.current);
                        pendingActiveMediaIdRef.current = null;
                    }

                    // Switch to library tab only if all uploads succeeded without errors
                    if (!hasErrors) {
                        setTab('library');
                    }
                },
            },
        );
    }, [successfulUploads, uploadFiles, pickerAction, pickerFilters]);

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

    // Derived states for upload UI
    const activeFiles = useMemo(
        () => uploadFiles.filter((f) => f.status !== 'staged'),
        [uploadFiles],
    );
    const stagedFiles = useMemo(
        () => uploadFiles.filter((f) => f.status === 'staged'),
        [uploadFiles],
    );
    const hasActiveUploads = activeFiles.some(
        (f) => f.status === 'uploading' || f.status === 'pending',
    );
    const isUploading = hasActiveUploads;

    const totalProgress =
        activeFiles.length > 0
            ? Math.round(activeFiles.reduce((sum, f) => sum + f.progress, 0) / activeFiles.length)
            : 0;

    const totalStagedSize = stagedFiles.reduce((sum, f) => sum + f.size, 0);

    const canChooseMultipleFiles = uploadSettings ? uploadSettings.max_files_per_upload > 1 : true;

    const clearAllStaged = useCallback(() => {
        setUploadFiles((prev) => {
            prev.forEach((f) => {
                if (f.status === 'staged' && f.previewUrl) {
                    URL.revokeObjectURL(f.previewUrl);
                }
            });
            return prev.filter((f) => f.status !== 'staged');
        });
    }, []);

    const removeUploadFile = useCallback((id: string) => {
        setUploadFiles((prev) => {
            const file = prev.find((f) => f.id === id);
            if (file?.previewUrl) {
                URL.revokeObjectURL(file.previewUrl);
            }
            return prev.filter((f) => f.id !== id);
        });
    }, []);

    const startUpload = useCallback(() => {
        setUploadFiles((prev) =>
            prev.map((f) => (f.status === 'staged' ? { ...f, status: 'pending' } : f)),
        );
    }, []);

    const isLoading = open && !pickerMedia;

    // ── Render ───────────────────────────────────────────────────

    return (
        <Dialog open={open} onOpenChange={handleDialogOpenChange}>
            <DialogContent
                className="flex h-[calc(100vh-2rem)] max-h-[calc(100vh-2rem)] flex-col p-3 sm:w-[min(calc(100vw-2rem),80rem)] sm:max-w-7xl sm:p-4"
                showCloseButton
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ImageIcon className="size-5 text-primary" />
                        {title}
                    </DialogTitle>
                </DialogHeader>

                <Tabs
                    value={tab}
                    onValueChange={(v) => setTab(v as 'upload' | 'library')}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <TabsList>
                        <TabsTrigger value="upload">
                            <UploadCloudIcon data-icon="inline-start" />
                            Upload
                        </TabsTrigger>
                        <TabsTrigger value="library">
                            <ImageIcon data-icon="inline-start" />
                            Library
                        </TabsTrigger>
                    </TabsList>

                    {/* ── Upload tab ───────────────────────────── */}
                    <TabsContent value="upload" className="flex min-h-0 flex-1 flex-col">
                        <MediaPickerUploadTab
                            uploadSettings={uploadSettings}
                            uploadFiles={uploadFiles}
                            isDragOver={isDragOver}
                            fileInputRef={fileInputRef}
                            hasActiveUploads={hasActiveUploads}
                            isUploading={isUploading}
                            stagedFiles={stagedFiles}
                            activeFiles={activeFiles}
                            totalStagedSize={totalStagedSize}
                            totalProgress={totalProgress}
                            canChooseMultipleFiles={canChooseMultipleFiles}
                            onDragEnter={handleDragEnter}
                            onDragLeave={handleDragLeave}
                            onDragOver={(e) => e.preventDefault()}
                            onDrop={handleDrop}
                            stageFiles={stageFiles}
                            removeUploadFile={removeUploadFile}
                            clearAllStaged={clearAllStaged}
                            startUpload={startUpload}
                        />
                    </TabsContent>

                    {/* ── Library tab ──────────────────────────── */}
                    <TabsContent value="library" className="flex min-h-0 flex-1 gap-4 overflow-hidden">
                        {isLoading ? (
                            <div className="flex flex-1 items-center justify-center py-16">
                                <div className="flex flex-col items-center gap-3 text-muted-foreground">
                                    <Loader2Icon className="size-8 animate-spin" />
                                    <span className="text-sm">Loading media…</span>
                                </div>
                            </div>
                        ) : (
                            <>
                                {/* Left: Media Grid */}
                                <MediaPickerGrid
                                    pickerMedia={pickerMedia}
                                    pickerFilters={pickerFilters}
                                    pickerAction={pickerAction}
                                    pickerStatistics={pickerStatistics}
                                    activeMediaId={activeMediaId}
                                    onMediaClick={handleMediaClick}
                                />

                                {/* Right: Media Details Panel */}
                                <MediaPickerDetailsPanel
                                    activeMedia={activeMedia}
                                    isEditing={isEditing}
                                    editedAltText={editedAltText}
                                    editedTitle={editedTitle}
                                    editedCaption={editedCaption}
                                    setEditedAltText={setEditedAltText}
                                    setEditedTitle={setEditedTitle}
                                    setEditedCaption={setEditedCaption}
                                    onSave={handleSaveMedia}
                                    onSelect={handleSelectMedia}
                                />
                            </>
                        )}
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
