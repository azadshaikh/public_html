'use client';

import { lazy, Suspense, useCallback, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

const MonacoEditor = lazy(() =>
    import('@/components/code-editor/monaco-editor').then((mod) => ({
        default: mod.MonacoEditor,
    })),
);

type SourceCodeDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    value: string;
    onApply: (html: string) => void;
};

export function SourceCodeDialog({
    open,
    onOpenChange,
    value,
    onApply,
}: SourceCodeDialogProps) {
    const [draft, setDraft] = useState(value);
    const [openState, setOpenState] = useState(false);

    if (open && !openState) {
        setDraft(value);
        setOpenState(true);
    } else if (!open && openState) {
        setOpenState(false);
    }

    const handleApply = useCallback(() => {
        onApply(draft);
        onOpenChange(false);
    }, [draft, onApply, onOpenChange]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl" showCloseButton={false}>
                <DialogHeader>
                    <DialogTitle>Source Code</DialogTitle>
                    <DialogDescription>
                        Edit the raw HTML source of this note. Changes will
                        replace the current editor content.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-[400px]">
                    <Suspense
                        fallback={
                            <div className="flex min-h-[400px] items-center justify-center gap-2 rounded-md border bg-muted/30">
                                <Spinner className="size-4" />
                                <span className="text-sm text-muted-foreground">
                                    Loading editor…
                                </span>
                            </div>
                        }
                    >
                        <MonacoEditor
                            value={draft}
                            onChange={setDraft}
                            language="html"
                            height={400}
                            editorClassName="rounded-md"
                            options={{
                                minimap: { enabled: false },
                                lineNumbers: 'on',
                                wordWrap: 'on',
                                formatOnPaste: true,
                                fontSize: 13,
                            }}
                        />
                    </Suspense>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button onClick={handleApply}>Apply</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
