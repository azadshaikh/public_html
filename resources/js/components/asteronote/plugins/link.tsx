'use client';

import { Link2Icon, UnlinkIcon } from 'lucide-react';
import * as React from 'react';

import type { AsteroNoteController } from '@/components/asteronote/asteronote-types';
import { ToolbarIconButton } from '@/components/asteronote/plugins/toolbar-icon-button';
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
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToolbarButton } from '@/components/ui/toolbar';

export function LinkPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    const [open, setOpen] = React.useState(false);
    const [url, setUrl] = React.useState('');
    const [text, setText] = React.useState('');
    const [target, setTarget] = React.useState('_blank');
    const [rel, setRel] = React.useState('noopener noreferrer');

    const syncFromSelection = React.useCallback(() => {
        editor.captureSelection();
        editor.restoreSelection();

        const selection = window.getSelection();
        const selectedText = selection?.toString() ?? '';
        const link = document.getSelection()?.anchorNode
            ? (document
                  .getSelection()
                  ?.anchorNode?.parentElement?.closest('a') ?? null)
            : null;

        setUrl(link?.getAttribute('href') ?? '');
        setText(link?.textContent ?? selectedText);
        setTarget(link?.getAttribute('target') ?? '_blank');
        const relAttr = link?.getAttribute('rel');
        setRel(relAttr || 'none');
    }, [editor]);

    const isEditing = Boolean(editor.formatState.link);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!url.trim()) return;

        editor.insertLink({
            text,
            url,
            target,
            rel: rel === 'none' ? undefined : rel,
        });
        setOpen(false);
    };

    return (
        <>
            <ToolbarIconButton
                disabled={editor.isCodeView}
                editor={editor}
                icon={<Link2Icon />}
                pressed={editor.formatState.link}
                tooltip={isEditing ? 'Edit link' : 'Insert link'}
                onPress={() => {
                    syncFromSelection();
                    setOpen(true);
                }}
            />
            {isEditing && (
                <ToolbarButton
                    disabled={editor.isCodeView}
                    tooltip="Remove link"
                    onMouseDown={(event: React.MouseEvent) => {
                        event.preventDefault();
                        editor.captureSelection();
                        editor.removeLink();
                        editor.refreshState();
                    }}
                >
                    <UnlinkIcon />
                </ToolbarButton>
            )}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <form onSubmit={handleSubmit}>
                        <DialogHeader>
                            <DialogTitle>
                                {isEditing ? 'Edit Link' : 'Insert Link'}
                            </DialogTitle>
                            <DialogDescription>
                                Add a destination URL and optional text to
                                display.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <FieldGroup>
                                <Field>
                                    <FieldLabel
                                        htmlFor={`${editor.id}-link-url`}
                                    >
                                        URL
                                    </FieldLabel>
                                    <Input
                                        id={`${editor.id}-link-url`}
                                        type="url"
                                        value={url}
                                        onChange={(event) =>
                                            setUrl(event.target.value)
                                        }
                                        placeholder="https://example.com"
                                        autoFocus
                                        required
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel
                                        htmlFor={`${editor.id}-link-text`}
                                    >
                                        Text to display
                                    </FieldLabel>
                                    <Input
                                        id={`${editor.id}-link-text`}
                                        value={text}
                                        onChange={(event) =>
                                            setText(event.target.value)
                                        }
                                        placeholder="Link text"
                                    />
                                    <FieldDescription>
                                        Leave empty to keep the current
                                        selection.
                                    </FieldDescription>
                                </Field>
                                <div className="grid grid-cols-2 gap-4">
                                    <Field>
                                        <FieldLabel
                                            htmlFor={`${editor.id}-link-target`}
                                        >
                                            Open in
                                        </FieldLabel>
                                        <Select
                                            value={target}
                                            onValueChange={setTarget}
                                        >
                                            <SelectTrigger
                                                id={`${editor.id}-link-target`}
                                            >
                                                <SelectValue placeholder="Select target" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="_self">
                                                    Current tab
                                                </SelectItem>
                                                <SelectItem value="_blank">
                                                    New tab (_blank)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <Field>
                                        <FieldLabel
                                            htmlFor={`${editor.id}-link-rel`}
                                        >
                                            Rel attribute
                                        </FieldLabel>
                                        <Select
                                            value={rel}
                                            onValueChange={setRel}
                                        >
                                            <SelectTrigger
                                                id={`${editor.id}-link-rel`}
                                            >
                                                <SelectValue placeholder="Select relation" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">
                                                    None
                                                </SelectItem>
                                                <SelectItem value="noopener noreferrer">
                                                    noopener noreferrer
                                                </SelectItem>
                                                <SelectItem value="nofollow">
                                                    nofollow
                                                </SelectItem>
                                                <SelectItem value="noopener noreferrer nofollow">
                                                    All three
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={!url.trim()}>
                                {isEditing ? 'Update link' : 'Insert link'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
