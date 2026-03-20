import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type BuilderCodeEditorDialogProps = {
    open: boolean;
    value: string;
    onChange: (value: string) => void;
    onClose: () => void;
    onApply: () => void;
};

export function BuilderCodeEditorDialog({
    open,
    value,
    onChange,
    onClose,
    onApply,
}: BuilderCodeEditorDialogProps) {
    return (
        <Dialog open={open} onOpenChange={(nextOpen) => { if (!nextOpen) onClose(); }}>
            <DialogContent className="flex h-[calc(100vh-4rem)] max-h-[calc(100vh-4rem)] flex-col gap-3 p-3 sm:w-[min(calc(100vw-4rem),72rem)] sm:max-w-6xl">
                <DialogHeader className="gap-1">
                    <DialogTitle>Edit Element Code</DialogTitle>
                </DialogHeader>
                <div className="min-h-0 flex-1" data-builder-shortcut-scope="element-code">
                    <MonacoEditor
                        value={value}
                        onChange={onChange}
                        language="html"
                        height="100%"
                    />
                </div>
                <DialogFooter className="-mx-3 -mb-3 gap-2 rounded-b-xl border-t bg-muted/35 px-3 py-2 sm:px-3">
                    <Button variant="outline" onClick={onClose}>Cancel</Button>
                    <Button onClick={onApply}>Apply</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}