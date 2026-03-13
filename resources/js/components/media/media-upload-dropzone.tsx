import { router } from '@inertiajs/react';
import {
    AlertCircleIcon,
    CheckCircleIcon,
    FileIcon,
    LoaderIcon,
    Trash2Icon,
    UploadCloudIcon,
    XIcon,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import type { UploadFile, UploadResponse, UploadSettings } from '@/types/media';

type MediaUploadDropzoneProps = {
    uploadSettings: UploadSettings;
    onUploadComplete?: () => void;
};

export function MediaUploadDropzone({
    uploadSettings,
    onUploadComplete,
}: MediaUploadDropzoneProps) {
    const [files, setFiles] = useState<UploadFile[]>([]);
    const [isDragOver, setIsDragOver] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const dragCounterRef = useRef(0);
    const uploadedIdsRef = useRef<Set<string>>(new Set());

    const acceptedTypes = uploadSettings.accepted_mime_types
        .split(',')
        .map((t) => t.trim());

    // ── Format file size ─────────────────────────────────────────────

    const formatSize = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
    };

    // ── Validate file ────────────────────────────────────────────────

    const validateFile = useCallback(
        (file: File): string | null => {
            if (!acceptedTypes.includes(file.type)) {
                return `File type "${file.type}" is not allowed. Accepted: ${uploadSettings.friendly_file_types}`;
            }
            if (file.size > uploadSettings.max_size_bytes) {
                return `File size exceeds ${uploadSettings.max_size_mb}MB limit`;
            }
            return null;
        },
        [acceptedTypes, uploadSettings],
    );

    // ── Create preview URL ───────────────────────────────────────────

    const createPreview = useCallback((file: File): string | undefined => {
        if (file.type.startsWith('image/')) {
            return URL.createObjectURL(file);
        }
        return undefined;
    }, []);

    // ── Upload single file via XHR ───────────────────────────────────

    const uploadSingleFile = useCallback(
        (uploadFile: UploadFile) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('file', uploadFile.file);

            const csrfMeta = document.head.querySelector(
                'meta[name="csrf-token"]',
            );
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const progress = Math.round((e.loaded / e.total) * 100);
                    setFiles((prev) =>
                        prev.map((f) =>
                            f.id === uploadFile.id
                                ? { ...f, progress, status: 'uploading' }
                                : f,
                        ),
                    );
                }
            };

            xhr.onload = () => {
                try {
                    const response: UploadResponse = JSON.parse(
                        xhr.responseText,
                    );
                    if (
                        xhr.status >= 200 &&
                        xhr.status < 300 &&
                        response.status === 1
                    ) {
                        setFiles((prev) =>
                            prev.map((f) =>
                                f.id === uploadFile.id
                                    ? {
                                          ...f,
                                          progress: 100,
                                          status: 'success',
                                          mediaId: response.file?.id,
                                          thumbnailUrl: response.file?.thumb,
                                      }
                                    : f,
                            ),
                        );
                    } else {
                        setFiles((prev) =>
                            prev.map((f) =>
                                f.id === uploadFile.id
                                    ? {
                                          ...f,
                                          status: 'error',
                                          error:
                                              response.error || 'Upload failed',
                                      }
                                    : f,
                            ),
                        );
                    }
                } catch {
                    setFiles((prev) =>
                        prev.map((f) =>
                            f.id === uploadFile.id
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
                setFiles((prev) =>
                    prev.map((f) =>
                        f.id === uploadFile.id
                            ? {
                                  ...f,
                                  status: 'error',
                                  error: 'Network error',
                              }
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
        [uploadSettings.upload_route],
    );

    // ── Stage files (no auto-upload) ─────────────────────────────────

    const stageFiles = useCallback(
        (fileList: FileList | File[]) => {
            const newFiles: UploadFile[] = Array.from(fileList).map((file) => {
                const error = validateFile(file);
                return {
                    id: crypto.randomUUID(),
                    file,
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    progress: 0,
                    status: error ? 'error' : 'staged',
                    error: error ?? undefined,
                    previewUrl: createPreview(file),
                };
            });

            // Clear finished uploads when staging new files so the
            // staging UI becomes visible again without manual dismiss
            setFiles((prev) => {
                const kept = prev.filter(
                    (f) => f.status !== 'success' && f.status !== 'error',
                );
                return [...kept, ...newFiles];
            });
            setIsUploading(false);
        },
        [validateFile, createPreview],
    );

    // ── Start uploading all staged files ─────────────────────────────

    const startUpload = useCallback(() => {
        setIsUploading(true);

        // Read current staged files, then update state and fire uploads
        // separately to avoid side effects inside React state setters
        // (React strict mode calls setters twice, which caused double uploads)
        setFiles((prev) => {
            return prev.map((f) =>
                f.status === 'staged' ? { ...f, status: 'pending' } : f,
            );
        });
    }, []);

    // ── Fire XHR for newly-pending files (effect, not inside setter) ─

    useEffect(() => {
        const pending = files.filter(
            (f) => f.status === 'pending' && !uploadedIdsRef.current.has(f.id),
        );
        pending.forEach((f) => {
            uploadedIdsRef.current.add(f.id);
            uploadSingleFile(f);
        });
    }, [files, uploadSingleFile]);

    // ── Drag events ──────────────────────────────────────────────────

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

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
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

    // ── Remove file from staging ─────────────────────────────────────

    const removeFile = useCallback((id: string) => {
        setFiles((prev) => {
            const file = prev.find((f) => f.id === id);
            if (file?.previewUrl) {
                URL.revokeObjectURL(file.previewUrl);
            }
            return prev.filter((f) => f.id !== id);
        });
    }, []);

    // ── Clear all files ──────────────────────────────────────────────

    const clearAll = useCallback(() => {
        setFiles((prev) => {
            prev.filter((f) => f.previewUrl).forEach((f) =>
                URL.revokeObjectURL(f.previewUrl!),
            );
            return [];
        });
        uploadedIdsRef.current.clear();
        setIsUploading(false);
    }, []);

    // ── Auto-reload datagrid when all uploads finish ─────────────────

    useEffect(() => {
        if (files.length === 0 || !isUploading) return;
        const hasPending = files.some(
            (f) => f.status === 'pending' || f.status === 'uploading',
        );
        const hasSuccess = files.some((f) => f.status === 'success');
        if (!hasPending && hasSuccess) {
            router.reload({ only: ['media', 'statistics', 'storageData'] });
            onUploadComplete?.();
        }
    }, [files, isUploading, onUploadComplete]);

    // ── Derived state ────────────────────────────────────────────────

    const stagedFiles = files.filter((f) => f.status === 'staged');
    const activeFiles = files.filter((f) =>
        ['pending', 'uploading', 'success', 'error'].includes(f.status),
    );
    const hasActiveUploads = files.some(
        (f) => f.status === 'pending' || f.status === 'uploading',
    );
    const totalStagedSize = stagedFiles.reduce((sum, f) => sum + f.size, 0);
    const totalProgress =
        activeFiles.length > 0
            ? Math.round(
                  activeFiles.reduce((sum, f) => sum + f.progress, 0) /
                      activeFiles.length,
              )
            : 0;

    return (
        <div className="space-y-4">
            {/* Drop Zone */}
            <div
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
                onClick={() => fileInputRef.current?.click()}
                className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors ${
                    isDragOver
                        ? 'border-primary bg-primary/5'
                        : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                }`}
            >
                <UploadCloudIcon
                    className={`mb-3 size-10 ${isDragOver ? 'text-primary' : 'text-muted-foreground'}`}
                />
                <p className="text-sm font-medium text-foreground">
                    Drop files here or click to browse
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    {uploadSettings.friendly_file_types} — Max{' '}
                    {uploadSettings.max_size_mb}MB per file
                </p>
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept={uploadSettings.accepted_mime_types}
                    onChange={(e) => {
                        if (e.target.files) {
                            stageFiles(e.target.files);
                            e.target.value = '';
                        }
                    }}
                    className="hidden"
                />
            </div>

            {/* Staged Files Preview (before upload) */}
            {stagedFiles.length > 0 && !isUploading && (
                <div className="rounded-lg border bg-card p-4">
                    {/* Thumbnail Grid */}
                    <div className="flex flex-wrap gap-3">
                        {stagedFiles.map((f) => (
                            <div
                                key={f.id}
                                className="group relative w-24 shrink-0"
                            >
                                {/* Remove button */}
                                <button
                                    type="button"
                                    onClick={() => removeFile(f.id)}
                                    className="absolute -top-1.5 -right-1.5 z-10 flex size-5 items-center justify-center rounded-full bg-destructive text-white shadow-sm"
                                >
                                    <XIcon className="size-3 stroke-[3]" />
                                </button>

                                {/* Thumbnail */}
                                <div className="aspect-square overflow-hidden rounded-lg border bg-muted">
                                    {f.previewUrl ? (
                                        <img
                                            src={f.previewUrl}
                                            alt={f.name}
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <div className="flex size-full items-center justify-center">
                                            <FileIcon className="size-8 text-muted-foreground/50" />
                                        </div>
                                    )}
                                    {f.status === 'error' && (
                                        <div className="absolute inset-0 flex items-center justify-center rounded-lg bg-destructive/10">
                                            <AlertCircleIcon className="size-5 text-destructive" />
                                        </div>
                                    )}
                                </div>

                                {/* File name & size */}
                                <p className="mt-1 truncate text-xs font-medium text-foreground">
                                    {f.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {formatSize(f.size)}
                                </p>
                            </div>
                        ))}
                    </div>

                    {/* Actions */}
                    <div className="mt-4 flex items-center justify-between border-t pt-4">
                        <p className="text-sm font-medium text-foreground">
                            {stagedFiles.length} file
                            {stagedFiles.length !== 1 ? 's' : ''} ready to
                            upload
                            <span className="ml-1.5 text-muted-foreground">
                                {formatSize(totalStagedSize)}
                            </span>
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={clearAll}
                            >
                                <Trash2Icon className="mr-1.5 size-3.5" />
                                Clear
                            </Button>
                            <Button size="sm" onClick={startUpload}>
                                <UploadCloudIcon className="mr-1.5 size-3.5" />
                                Upload {stagedFiles.length} File
                                {stagedFiles.length !== 1 ? 's' : ''}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Upload Progress (during/after upload) */}
            {isUploading && activeFiles.length > 0 && (
                <div className="rounded-lg border bg-card p-4">
                    {/* Overall progress */}
                    {hasActiveUploads && (
                        <div className="mb-3 space-y-1.5">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-medium text-foreground">
                                    Uploading... {totalProgress}%
                                </span>
                                <span className="text-muted-foreground">
                                    {
                                        activeFiles.filter(
                                            (f) => f.status === 'success',
                                        ).length
                                    }{' '}
                                    / {activeFiles.length} complete
                                </span>
                            </div>
                            <Progress value={totalProgress} className="h-2" />
                        </div>
                    )}

                    {/* File List */}
                    <div className="max-h-60 space-y-2 overflow-y-auto">
                        {activeFiles.map((f) => (
                            <div
                                key={f.id}
                                className="flex items-center gap-3 rounded-lg border bg-background p-2.5"
                            >
                                {/* Preview */}
                                <div className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded bg-muted">
                                    {f.thumbnailUrl || f.previewUrl ? (
                                        <img
                                            src={f.thumbnailUrl || f.previewUrl}
                                            alt={f.name}
                                            className="size-9 object-cover"
                                        />
                                    ) : (
                                        <FileIcon className="size-4 text-muted-foreground" />
                                    )}
                                </div>

                                {/* Info */}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="truncate text-sm font-medium text-foreground">
                                            {f.name}
                                        </span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {formatSize(f.size)}
                                        </span>
                                    </div>
                                    {f.status === 'uploading' && (
                                        <Progress
                                            value={f.progress}
                                            className="mt-1 h-1.5"
                                        />
                                    )}
                                    {f.status === 'error' && (
                                        <p className="mt-0.5 text-xs text-destructive">
                                            {f.error}
                                        </p>
                                    )}
                                </div>

                                {/* Status */}
                                <div className="shrink-0">
                                    {f.status === 'pending' && (
                                        <LoaderIcon className="size-4 animate-spin text-muted-foreground" />
                                    )}
                                    {f.status === 'uploading' && (
                                        <LoaderIcon className="size-4 animate-spin text-primary" />
                                    )}
                                    {f.status === 'success' && (
                                        <CheckCircleIcon className="size-4 text-green-500" />
                                    )}
                                    {f.status === 'error' && (
                                        <AlertCircleIcon className="size-4 text-destructive" />
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Done state */}
                    {!hasActiveUploads && (
                        <div className="mt-3 flex items-center justify-between border-t pt-3">
                            <p className="text-sm font-medium text-green-600">
                                <CheckCircleIcon className="mr-1.5 inline size-4" />
                                Upload complete
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={clearAll}
                            >
                                Dismiss
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
