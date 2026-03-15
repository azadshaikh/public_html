'use client';

import { Link2Icon } from 'lucide-react';
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

export function LinkPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    const [open, setOpen] = React.useState(false);
    const [url, setUrl] = React.useState('');
    const [text, setText] = React.useState('');
    const [target, setTarget] = React.useState('_blank');
    const [rel, setRel] = React.useState('noopener nofollow');

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
        setRel(link?.getAttribute('rel') ?? 'noopener nofollow');
    }, [editor]);

    return (
        <>
            <ToolbarIconButton
                disabled={editor.isCodeView}
                editor={editor}
                icon={<Link2Icon />}
                pressed={editor.formatState.link}
                tooltip="Insert or edit link"
                onPress={() => {
                    syncFromSelection();
                    setOpen(true);
                }}
            />
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Insert or edit link</DialogTitle>
                        <DialogDescription>
                            Add a URL, optional link text, and advanced target
                            attributes.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-link-url`}>
                                URL
                            </FieldLabel>
                            <Input
                                id={`${editor.id}-link-url`}
                                value={url}
                                onChange={(event) => setUrl(event.target.value)}
                                placeholder="https://example.com"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-link-text`}>
                                Text
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
                                Leave this empty to keep the current selection
                                text.
                            </FieldDescription>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-link-target`}>
                                Target
                            </FieldLabel>
                            <Input
                                id={`${editor.id}-link-target`}
                                value={target}
                                onChange={(event) =>
                                    setTarget(event.target.value)
                                }
                                placeholder="_blank"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-link-rel`}>
                                rel
                            </FieldLabel>
                            <Input
                                id={`${editor.id}-link-rel`}
                                value={rel}
                                onChange={(event) => setRel(event.target.value)}
                                placeholder="noopener nofollow"
                            />
                        </Field>
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                editor.removeLink();
                                setOpen(false);
                            }}
                        >
                            Remove link
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={!url.trim()}
                            onClick={() => {
                                editor.insertLink({ text, url, target, rel });
                                setOpen(false);
                            }}
                        >
                            Apply link
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
