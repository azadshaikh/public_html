'use client';

import { VideoIcon } from 'lucide-react';
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

// Helper to check if URL is a supported video format to show preview
function isDirectVideo(url: string) {
    return /\.(mp4|webm|ogg)(\?.*)?$/i.test(url);
}

// Helper to extract Youtube ID
function getYoutubeId(url: string) {
    const match = /(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{6,})/i.exec(url);
    return match ? match[1] : null;
}

// Helper to extract Vimeo ID
function getVimeoId(url: string) {
    const match = /vimeo\.com\/(?:channels\/\w+\/|groups\/\w+\/videos\/)?([0-9]+)/i.exec(url);
    return match ? match[1] : null;
}

export function VideoPluginControl({
    editor,
}: {
    editor: AsteroNoteController;
}) {
    const [open, setOpen] = React.useState(false);
    const [url, setUrl] = React.useState('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!url.trim()) return;

        editor.insertVideo({ url });
        setOpen(false);
    };

    const renderPreview = () => {
        if (!url) return null;

        const ytId = getYoutubeId(url);
        if (ytId) {
            return (
                <div className="aspect-video w-full rounded-md overflow-hidden bg-muted">
                    <iframe
                        src={`https://www.youtube.com/embed/${ytId}`}
                        className="w-full h-full border-0"
                        title="Video preview"
                        allowFullScreen
                    />
                </div>
            );
        }

        const vmId = getVimeoId(url);
        if (vmId) {
            return (
                <div className="aspect-video w-full rounded-md overflow-hidden bg-muted">
                    <iframe
                        src={`https://player.vimeo.com/video/${vmId}`}
                        className="w-full h-full border-0"
                        title="Video preview"
                        allowFullScreen
                    />
                </div>
            );
        }

        if (isDirectVideo(url)) {
            return (
                <div className="aspect-video w-full rounded-md overflow-hidden bg-black flex items-center justify-center">
                    <video src={url} controls className="max-w-full max-h-full" />
                </div>
            );
        }

        // URL entered but format not recognized for preview
        return (
            <div className="aspect-video w-full rounded-md border border-dashed border-muted-foreground/30 bg-muted/20 flex flex-col items-center justify-center text-muted-foreground">
                <VideoIcon className="size-8 mb-2 opacity-50" />
                <span className="text-sm">No preview available</span>
            </div>
        );
    };

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
                <DialogContent className="sm:max-w-[500px]">
                    <form onSubmit={handleSubmit}>
                        <DialogHeader>
                            <DialogTitle>Insert Video</DialogTitle>
                            <DialogDescription>
                                Add a YouTube, Vimeo, Dailymotion link, or a direct URL to an mp4/webm file.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            {url && (
                                <div className="mb-2">
                                    {renderPreview()}
                                </div>
                            )}
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor={`${editor.id}-video-url`}>
                                        Video URL
                                    </FieldLabel>
                                    <Input
                                        id={`${editor.id}-video-url`}
                                        type="url"
                                        value={url}
                                        onChange={(event) => setUrl(event.target.value)}
                                        placeholder="https://www.youtube.com/watch?v=..."
                                        autoFocus
                                        required
                                    />
                                    <FieldDescription>
                                        The video will be embedded responsively in the content.
                                    </FieldDescription>
                                </Field>
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
                                disabled={!url.trim()}
                            >
                                Insert Video
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
