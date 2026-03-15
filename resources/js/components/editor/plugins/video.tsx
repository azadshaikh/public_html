'use client';

import { VideoIcon } from 'lucide-react';
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

export function VideoPluginControl({ editor }: { editor: AsteroNoteController }) {
    const [open, setOpen] = React.useState(false);
    const [url, setUrl] = React.useState('');

    return (
        <>
            <ToolbarIconButton
                disabled={editor.isCodeView}
                editor={editor}
                icon={<VideoIcon />}
                tooltip="Insert video"
                onPress={() => {
                    editor.captureSelection();
                    setUrl('');
                    setOpen(true);
                }}
            />
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Insert video</DialogTitle>
                        <DialogDescription>
                            Supports YouTube, Vimeo, Dailymotion, and direct
                            mp4/webm/ogg files.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor={`${editor.id}-video-url`}>
                                Video URL
                            </FieldLabel>
                            <Input
                                id={`${editor.id}-video-url`}
                                value={url}
                                onChange={(event) => setUrl(event.target.value)}
                                placeholder="https://www.youtube.com/watch?v=..."
                            />
                        </Field>
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
                                editor.insertVideo({ url });
                                setOpen(false);
                            }}
                        >
                            Insert video
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
