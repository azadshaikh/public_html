'use client';

import { ImagePlusIcon } from 'lucide-react';
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
import { Field, FieldGroup, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export function ImagePluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    const [open, setOpen] = React.useState(false);
    const [src, setSrc] = React.useState('');
    const [alt, setAlt] = React.useState('');
    const [srcset, setSrcset] = React.useState('');
    const [sizes, setSizes] = React.useState('');
    const [width, setWidth] = React.useState('');
    const [height, setHeight] = React.useState('');

    const reset = React.useCallback(() => {
        setSrc('');
        setAlt('');
        setSrcset('');
        setSizes('');
        setWidth('');
        setHeight('');
    }, []);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!src.trim()) return;

        editor.insertImage({
            src,
            alt,
            srcset,
            sizes,
            width,
            height,
        });
        setOpen(false);
    };

    return (
        <>
            <ToolbarIconButton
                disabled={editor.isCodeView}
                editor={editor}
                icon={<ImagePlusIcon />}
                tooltip="Insert image"
                onPress={() => {
                    editor.captureSelection();
                    reset();
                    setOpen(true);
                }}
            />
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <form onSubmit={handleSubmit}>
                        <DialogHeader>
                            <DialogTitle>Insert Image</DialogTitle>
                            <DialogDescription>
                                Add an image URL and optional responsive attributes.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4 max-h-[60vh] overflow-y-auto px-1">
                            {src && (
                                <div className="rounded-md border bg-muted/30 p-2 flex justify-center items-center mb-2 h-32 overflow-hidden relative">
                                    {/* eslint-disable-next-line @next/next/no-img-element */}
                                    <img
                                        src={src}
                                        alt={alt || "Preview"}
                                        className="max-h-full max-w-full object-contain"
                                        onError={(e) => {
                                            (e.target as HTMLImageElement).style.display = 'none';
                                            e.currentTarget.parentElement!.innerHTML = '<span class="text-sm text-muted-foreground">Invalid image URL</span>';
                                        }}
                                    />
                                </div>
                            )}

                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor={`${editor.id}-image-src`}>
                                        Image URL <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <Input
                                        id={`${editor.id}-image-src`}
                                        type="url"
                                        value={src}
                                        onChange={(event) => setSrc(event.target.value)}
                                        placeholder="https://example.com/image.jpg"
                                        autoFocus
                                        required
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor={`${editor.id}-image-alt`}>
                                        Alt text
                                    </FieldLabel>
                                    <Input
                                        id={`${editor.id}-image-alt`}
                                        value={alt}
                                        onChange={(event) => setAlt(event.target.value)}
                                        placeholder="Describe the image for screen readers"
                                    />
                                </Field>

                                <div className="grid grid-cols-2 gap-4">
                                    <Field>
                                        <FieldLabel htmlFor={`${editor.id}-image-width`}>
                                            Width
                                        </FieldLabel>
                                        <Input
                                            id={`${editor.id}-image-width`}
                                            value={width}
                                            onChange={(event) => setWidth(event.target.value)}
                                            placeholder="e.g. 800"
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor={`${editor.id}-image-height`}>
                                            Height
                                        </FieldLabel>
                                        <Input
                                            id={`${editor.id}-image-height`}
                                            value={height}
                                            onChange={(event) => setHeight(event.target.value)}
                                            placeholder="e.g. 600"
                                        />
                                    </Field>
                                </div>

                                <details className="group [&_summary::-webkit-details-marker]:hidden">
                                    <summary className="flex cursor-pointer items-center text-sm font-medium text-muted-foreground outline-none hover:text-foreground">
                                        <span className="mr-2 transition-transform group-open:rotate-90">
                                            ▶
                                        </span>
                                        Advanced responsive attributes
                                    </summary>
                                    <div className="mt-4 flex flex-col gap-4 pl-4 border-l-2 border-muted">
                                        <Field>
                                            <FieldLabel htmlFor={`${editor.id}-image-srcset`}>
                                                srcset
                                            </FieldLabel>
                                            <Textarea
                                                id={`${editor.id}-image-srcset`}
                                                rows={2}
                                                value={srcset}
                                                onChange={(event) => setSrcset(event.target.value)}
                                                placeholder="image-320w.jpg 320w, image-480w.jpg 480w"
                                            />
                                            <FieldDescription>Multiple resolutions for different screen sizes</FieldDescription>
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor={`${editor.id}-image-sizes`}>
                                                sizes
                                            </FieldLabel>
                                            <Input
                                                id={`${editor.id}-image-sizes`}
                                                value={sizes}
                                                onChange={(event) => setSizes(event.target.value)}
                                                placeholder="(max-width: 320px) 280px, 800px"
                                            />
                                            <FieldDescription>Media conditions indicating image layout width</FieldDescription>
                                        </Field>
                                    </div>
                                </details>
                            </FieldGroup>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-0 mt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={!src.trim()}
                            >
                                Insert Image
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
