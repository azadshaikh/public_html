import type { FormEvent } from 'react';
import { RotateCcwIcon } from 'lucide-react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { Button } from '@/components/ui/button';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { CodeEditorState } from '../../pages/cms/themes/customizer/types';

type ThemeCustomizerDialogsProps = {
    activeCodeEditor: CodeEditorState | null;
    onCodeEditorChange: (state: CodeEditorState | null) => void;
    onApplyCodeEditor: () => void;
    importDialogOpen: boolean;
    onImportDialogOpenChange: (open: boolean) => void;
    onImportSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onImportFileChange: (file: File | null) => void;
    resetDialogOpen: boolean;
    onResetDialogOpenChange: (open: boolean) => void;
    onReset: () => void;
};

export function ThemeCustomizerDialogs({
    activeCodeEditor,
    onCodeEditorChange,
    onApplyCodeEditor,
    importDialogOpen,
    onImportDialogOpenChange,
    onImportSubmit,
    onImportFileChange,
    resetDialogOpen,
    onResetDialogOpenChange,
    onReset,
}: ThemeCustomizerDialogsProps) {
    return (
        <>
            <Dialog
                open={activeCodeEditor !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        onCodeEditorChange(null);
                    }
                }}
            >
                <DialogContent className="max-w-[min(96vw,1100px)] overflow-hidden p-0">
                    <DialogHeader className="border-b px-6 py-4">
                        <DialogTitle>{activeCodeEditor?.label}</DialogTitle>
                        <DialogDescription>
                            Edit and apply code changes to the live preview before saving the customizer.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="px-6 py-5">
                        <MonacoEditor
                            value={activeCodeEditor?.value ?? ''}
                            onChange={(value) =>
                                onCodeEditorChange(
                                    activeCodeEditor
                                        ? { ...activeCodeEditor, value }
                                        : activeCodeEditor,
                                )
                            }
                            language={activeCodeEditor?.language ?? 'plaintext'}
                            height="min(68vh,720px)"
                        />
                    </div>
                    <DialogFooter className="border-t px-6 py-4">
                        <Button variant="outline" onClick={() => onCodeEditorChange(null)}>
                            Cancel
                        </Button>
                        <Button onClick={onApplyCodeEditor}>Apply changes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={importDialogOpen} onOpenChange={onImportDialogOpenChange}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Import theme settings</DialogTitle>
                        <DialogDescription>
                            Upload a JSON export to replace the current customizer state for this theme.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="flex flex-col gap-4" onSubmit={onImportSubmit}>
                        <Field>
                            <FieldLabel htmlFor="settings-file">Settings file</FieldLabel>
                            <Input
                                id="settings-file"
                                type="file"
                                accept=".json,application/json"
                                onChange={(event) =>
                                    onImportFileChange(event.target.files?.[0] ?? null)
                                }
                            />
                            <FieldDescription>
                                Use a JSON file exported from the theme customizer.
                            </FieldDescription>
                        </Field>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onImportDialogOpenChange(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Import</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmationDialog
                open={resetDialogOpen}
                onOpenChange={onResetDialogOpenChange}
                title="Reset theme settings?"
                description="This will restore the customizer fields to their default values for the active theme."
                confirmLabel="Reset settings"
                icon={<RotateCcwIcon className="size-4" />}
                confirmClassName="bg-destructive text-white hover:bg-destructive/90"
                onConfirm={onReset}
            />
        </>
    );
}