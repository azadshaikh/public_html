import type { UseHttpForm } from '@inertiajs/react';
import {
    AlertCircleIcon,
    HistoryIcon,
    PencilIcon,
    PlusIcon,
    UploadIcon,
} from 'lucide-react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Spinner } from '@/components/ui/spinner';
import type {
    DeleteTarget,
    GenericResponse,
    GitCommit,
    NewEntityMode,
    UploadPayload,
} from '../../pages/cms/themes/editor/types';

type CreateDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: NewEntityMode;
    path: string;
    onPathChange: (path: string) => void;
    onSubmit: () => void;
    processing: boolean;
    canEdit: boolean;
};

export function CreateDialog({
    open,
    onOpenChange,
    mode,
    path,
    onPathChange,
    onSubmit,
    processing,
    canEdit,
}: CreateDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{mode === 'file' ? 'Create new file' : 'Create new folder'}</DialogTitle>
                    <DialogDescription>
                        Enter the full relative path. Use folders in the path if you want to nest the new item.
                    </DialogDescription>
                </DialogHeader>
                <Field>
                    <FieldLabel htmlFor="new-entity-path">Relative path</FieldLabel>
                    <Input id="new-entity-path" value={path} onChange={(event) => onPathChange(event.target.value)} placeholder={mode === 'file' ? 'templates/home.twig' : 'assets/images'} />
                    <FieldDescription>
                        {mode === 'file' ? 'Supported files: twig, css, js, json, md, html, xml, scss.' : 'Protected folders cannot be deleted later.'}
                    </FieldDescription>
                </Field>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                    <Button onClick={onSubmit} disabled={processing || !canEdit || path.trim() === ''}>
                        {processing ? <Spinner /> : <PlusIcon data-icon="inline-start" />}
                        Create
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type RenameDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    path: string;
    onPathChange: (path: string) => void;
    onSubmit: () => void;
    processing: boolean;
    canEdit: boolean;
};

export function RenameDialog({
    open,
    onOpenChange,
    path,
    onPathChange,
    onSubmit,
    processing,
    canEdit,
}: RenameDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rename item</DialogTitle>
                    <DialogDescription>Update the full relative path for the selected file or folder.</DialogDescription>
                </DialogHeader>
                <Field>
                    <FieldLabel htmlFor="rename-path">New path</FieldLabel>
                    <Input id="rename-path" value={path} onChange={(event) => onPathChange(event.target.value)} />
                </Field>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                    <Button onClick={onSubmit} disabled={processing || !canEdit || path.trim() === ''}>
                        {processing ? <Spinner /> : <PencilIcon data-icon="inline-start" />}
                        Rename
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type UploadDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    targetPath: string;
    onTargetPathChange: (path: string) => void;
    uploadRequest: UseHttpForm<UploadPayload, GenericResponse>;
    onSubmit: () => void;
    canEdit: boolean;
};

export function UploadDialog({
    open,
    onOpenChange,
    targetPath,
    onTargetPathChange,
    uploadRequest,
    onSubmit,
    canEdit,
}: UploadDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Upload file</DialogTitle>
                    <DialogDescription>Upload a file into the current theme directory structure.</DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-4">
                    <Field>
                        <FieldLabel htmlFor="upload-path">Target folder</FieldLabel>
                        <Input id="upload-path" value={targetPath} onChange={(event) => onTargetPathChange(event.target.value)} placeholder="assets/images" />
                        <FieldDescription>Leave blank to upload to the theme root.</FieldDescription>
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="upload-file">File</FieldLabel>
                        <Input
                            id="upload-file"
                            type="file"
                            onChange={(event) => {
                                uploadRequest.setData('file', event.currentTarget.files?.[0] ?? null);
                            }}
                        />
                        <FieldDescription>Maximum upload size is 10MB.</FieldDescription>
                    </Field>
                    {uploadRequest.progress ? (
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span>Uploading…</span>
                                <span>{uploadRequest.progress.percentage}%</span>
                            </div>
                            <Progress value={uploadRequest.progress.percentage} />
                        </div>
                    ) : null}
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                    <Button onClick={onSubmit} disabled={uploadRequest.processing || uploadRequest.data.file === null || !canEdit}>
                        {uploadRequest.processing ? <Spinner /> : <UploadIcon data-icon="inline-start" />}
                        Upload
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type DeleteConfirmDialogProps = {
    target: DeleteTarget | null;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    processing: boolean;
    canDelete: boolean;
};

export function DeleteConfirmDialog({
    target,
    onOpenChange,
    onConfirm,
    processing,
    canDelete,
}: DeleteConfirmDialogProps) {
    return (
        <AlertDialog open={target !== null} onOpenChange={(open) => !open && onOpenChange(false)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia><AlertCircleIcon className="size-5" /></AlertDialogMedia>
                    <AlertDialogTitle>Delete {target?.type ?? 'item'}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        {target ? `This will permanently remove ${target.path}. This action cannot be undone.` : 'This action cannot be undone.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Cancel</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" disabled={processing || !canDelete} onClick={onConfirm}>
                        {processing ? <Spinner /> : null}
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

type HistoryDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    themeName: string;
    commits: GitCommit[];
};

export function HistoryDialog({
    open,
    onOpenChange,
    themeName,
    commits,
}: HistoryDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Theme history</DialogTitle>
                    <DialogDescription>Recent git commits for {themeName}.</DialogDescription>
                </DialogHeader>
                <ScrollArea className="max-h-[60vh]">
                    <div className="flex flex-col gap-3 pr-4">
                        {commits.length === 0 ? (
                            <Empty className="border-0 px-0">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon"><HistoryIcon /></EmptyMedia>
                                    <EmptyTitle>No commits yet</EmptyTitle>
                                    <EmptyDescription>Once changes are committed, they will appear here.</EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            commits.map((commit) => (
                                <div key={commit.hash} className="rounded-lg border p-3">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <p className="font-medium">{commit.subject}</p>
                                            <p className="text-xs text-muted-foreground">{commit.author_name} • {new Date(commit.date).toLocaleString()}</p>
                                        </div>
                                        <Badge variant="outline">{commit.hash.slice(0, 7)}</Badge>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </ScrollArea>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
