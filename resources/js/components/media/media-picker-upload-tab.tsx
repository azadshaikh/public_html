import {
    AlertCircleIcon,
    CheckCircleIcon,
    FileIcon,
    Loader2Icon,
    LoaderIcon,
    Trash2Icon,
    UploadCloudIcon,
    XIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';
import type { UploadSettings } from '@/types/media';
import { formatSize, getFileTypeIcon, isImageMime } from './media-picker-utils';

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
};

type MediaPickerUploadTabProps = {
    uploadSettings: UploadSettings | null;
    uploadFiles: UploadingFile[];
    isDragOver: boolean;
    fileInputRef: React.RefObject<HTMLInputElement | null>;
    hasActiveUploads: boolean;
    isUploading: boolean;
    stagedFiles: UploadingFile[];
    activeFiles: UploadingFile[];
    totalStagedSize: number;
    totalProgress: number;
    canChooseMultipleFiles: boolean;
    onDragEnter: (e: React.DragEvent) => void;
    onDragLeave: (e: React.DragEvent) => void;
    onDragOver: (e: React.DragEvent) => void;
    onDrop: (e: React.DragEvent) => void;
    stageFiles: (files: FileList | File[]) => void;
    removeUploadFile: (id: string) => void;
    clearAllStaged: () => void;
    clearCompleted: () => void;
    startUpload: () => void;
};

export function MediaPickerUploadTab({
    uploadSettings,
    uploadFiles,
    isDragOver,
    fileInputRef,
    hasActiveUploads,
    isUploading,
    stagedFiles,
    activeFiles,
    totalStagedSize,
    totalProgress,
    canChooseMultipleFiles,
    onDragEnter,
    onDragLeave,
    onDragOver,
    onDrop,
    stageFiles,
    removeUploadFile,
    clearAllStaged,
    clearCompleted,
    startUpload,
}: MediaPickerUploadTabProps) {
    return (
        <div className="flex h-full flex-col gap-4 overflow-hidden p-4">
            {/* Drop Zone */}
            <div
                onDragEnter={onDragEnter}
                onDragLeave={onDragLeave}
                onDragOver={onDragOver}
                onDrop={onDrop}
                onClick={() => fileInputRef.current?.click()}
                className={cn(
                    'flex shrink-0 cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors',
                    isDragOver
                        ? 'border-primary bg-primary/5'
                        : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50',
                    isUploading && 'pointer-events-none opacity-50',
                )}
            >
                <UploadCloudIcon
                    className={cn(
                        'mb-3 size-10',
                        isDragOver ? 'text-primary' : 'text-muted-foreground',
                    )}
                />
                <p className="text-sm font-medium text-foreground">
                    Drop files here or click to browse
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    {uploadSettings
                        ? `${uploadSettings.friendly_file_types} — Max ${uploadSettings.max_size_mb}MB per file — Up to ${uploadSettings.max_files_per_upload} files at once`
                        : 'Loading settings…'}
                </p>
                <input
                    key={uploadSettings?.max_files_per_upload ?? 1}
                    ref={fileInputRef}
                    type="file"
                    multiple={canChooseMultipleFiles}
                    accept={uploadSettings?.accepted_mime_types ?? ''}
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
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        removeUploadFile(f.id);
                                    }}
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
                                onClick={clearAllStaged}
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
            {(isUploading || activeFiles.length > 0) && (
                <div className="flex min-h-0 flex-1 flex-col rounded-lg border bg-card p-4">
                    {/* Overall progress / Actions */}
                    <div className="mb-3 space-y-1.5">
                        <div className="flex items-center justify-between text-sm">
                            {isUploading ? (
                                <span className="font-medium text-foreground">
                                    Uploading... {totalProgress}%
                                </span>
                            ) : (
                                <span className="font-medium text-foreground">
                                    Uploads finished
                                </span>
                            )}
                            <div className="flex items-center gap-3">
                                <span className="text-muted-foreground">
                                    {
                                        activeFiles.filter(
                                            (f) => f.status === 'success',
                                        ).length
                                    }{' '}
                                    / {activeFiles.length} complete
                                </span>
                                {!isUploading && activeFiles.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={clearCompleted}
                                    >
                                        Dismiss
                                    </Button>
                                )}
                            </div>
                        </div>
                        {isUploading && (
                            <Progress value={totalProgress} className="h-2" />
                        )}
                    </div>

                    {/* File List */}
                    <ScrollArea className="min-h-0 flex-1">
                        <div className="space-y-2 pr-4">
                            {activeFiles.map((uf) => (
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
                                        {uf.type.startsWith('image/') &&
                                        uf.previewUrl ? (
                                            <img
                                                src={uf.previewUrl}
                                                alt={uf.name}
                                                className="size-10 object-cover"
                                            />
                                        ) : (
                                            getFileTypeIcon(
                                                uf.type,
                                                'size-5 text-muted-foreground',
                                            )
                                        )}
                                    </div>

                                    {/* Info */}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate text-sm font-medium text-foreground">
                                                {uf.name}
                                            </span>
                                            <span className="shrink-0 text-xs text-muted-foreground">
                                                {formatSize(uf.size)}
                                            </span>
                                        </div>
                                        {uf.status === 'uploading' && (
                                            <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full bg-primary transition-all duration-300"
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

                                    {/* Status Icon */}
                                    <div className="flex shrink-0 items-center gap-2">
                                        {uf.status === 'pending' && (
                                            <Loader2Icon className="size-4 animate-spin text-muted-foreground" />
                                        )}
                                        {uf.status === 'uploading' && (
                                            <span className="text-xs font-medium text-primary">
                                                {uf.progress}%
                                            </span>
                                        )}
                                        {uf.status === 'success' && (
                                            <CheckCircleIcon className="size-5 text-green-500" />
                                        )}
                                        {uf.status === 'error' && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8 text-muted-foreground hover:text-destructive"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    removeUploadFile(uf.id);
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
                </div>
            )}
        </div>
    );
}
