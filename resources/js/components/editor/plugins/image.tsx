'use client';

import { ImagePlusIcon } from 'lucide-react';
import * as React from 'react';

import type { AsteroNoteController } from '@/components/editor/asteronote-types';
import { ToolbarIconButton } from '@/components/editor/plugins/toolbar-icon-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export function ImagePluginControl({ editor }: { editor: AsteroNoteController }) {
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
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Insert image</DialogTitle>
                        <DialogDescription>
                            Add an image URL or CDN asset and optional
                            responsive attributes.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-image-src`}>
                                Image URL
                            </FieldLabel>
                            <Input
                                id={`${editor.id}-image-src`}
                                value={src}
                                onChange={(event) => setSrc(event.target.value)}
                                placeholder="https://cdn.example.com/hero.jpg"
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
                                placeholder="Describe the image"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-image-srcset`}>
                                srcset
                            </FieldLabel>
                            <Textarea
                                id={`${editor.id}-image-srcset`}
                                rows={3}
                                value={srcset}
                                onChange={(event) =>
                                    setSrcset(event.target.value)
                                }
                                placeholder="https://... 640w, https://... 1280w"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-image-sizes`}>
                                sizes
                            </FieldLabel>
                            <Input
                                id={`${editor.id}-image-sizes`}
                                value={sizes}
                                onChange={(event) =>
                                    setSizes(event.target.value)
                                }
                                placeholder="(max-width: 768px) 100vw, 768px"
                            />
                        </Field>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Field>
                                <FieldLabel
                                    htmlFor={`${editor.id}-image-width`}
                                >
                                    Width
                                </FieldLabel>
                                <Input
                                    id={`${editor.id}-image-width`}
                                    value={width}
                                    onChange={(event) =>
                                        setWidth(event.target.value)
                                    }
                                    placeholder="1200"
                                />
                            </Field>
                            <Field>
                                <FieldLabel
                                    htmlFor={`${editor.id}-image-height`}
                                >
                                    Height
                                </FieldLabel>
                                <Input
                                    id={`${editor.id}-image-height`}
                                    value={height}
                                    onChange={(event) =>
                                        setHeight(event.target.value)
                                    }
                                    placeholder="800"
                                />
                            </Field>
                        </div>
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={() => {
                                editor.insertImage({
                                    src,
                                    alt,
                                    srcset,
                                    sizes,
                                    width,
                                    height,
                                });
                                setOpen(false);
                            }}
                        >
                            Insert image
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
