import { ImageIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { MediaListItem } from '@/types/media';
import { getFileExtension, getFileTypeIcon, isImageMime } from './media-picker-utils';

type MediaPickerDetailsPanelProps = {
    activeMedia: MediaListItem | null;
    isEditing: boolean;
    editedAltText: string;
    editedTitle: string;
    editedCaption: string;
    setEditedAltText: (val: string) => void;
    setEditedTitle: (val: string) => void;
    setEditedCaption: (val: string) => void;
    onSave: () => void;
    onSelect: () => void;
};

export function MediaPickerDetailsPanel({
    activeMedia,
    isEditing,
    editedAltText,
    editedTitle,
    editedCaption,
    setEditedAltText,
    setEditedTitle,
    setEditedCaption,
    onSave,
    onSelect,
}: MediaPickerDetailsPanelProps) {
    return (
        <div className="flex w-80 flex-col border-l">
            <div className="border-b px-4 py-3">
                <h3 className="text-sm font-semibold">Media Details</h3>
            </div>

            {!activeMedia ? (
                /* Empty State */
                <div className="flex flex-1 flex-col items-center justify-center gap-3 px-4 text-center">
                    <ImageIcon className="size-12 text-muted-foreground/40" />
                    <p className="text-sm text-muted-foreground">Select a file to view details</p>
                </div>
            ) : (
                /* Details Content */
                <ScrollArea className="flex-1 w-full" type="auto">
                    <div className="flex w-full flex-col space-y-4 p-4">
                        {/* Preview */}
                        <div className="flex h-48 items-center justify-center overflow-hidden rounded-lg border bg-muted p-2">
                            {isImageMime(activeMedia.mime_type) && activeMedia.thumbnail_url ? (
                                <img
                                    src={activeMedia.thumbnail_url}
                                    alt={activeMedia.alt_text || activeMedia.name}
                                    className="max-h-full max-w-full object-contain"
                                />
                            ) : (
                                <div className="flex items-center justify-center">
                                    {getFileTypeIcon(
                                        activeMedia.mime_type,
                                        'size-16 text-muted-foreground/40',
                                    )}
                                </div>
                            )}
                        </div>

                        {/* File Type */}
                        <div>
                            <p className="text-xs text-muted-foreground">
                                .{getFileExtension(activeMedia.file_name)}
                            </p>
                        </div>

                        {/* Alt Text */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-medium">Alt Text</label>
                            {isEditing ? (
                                <input
                                    type="text"
                                    value={editedAltText}
                                    onChange={(e) => setEditedAltText(e.target.value)}
                                    className="w-full rounded-md border px-3 py-1.5 text-sm"
                                    placeholder="Describe the image..."
                                />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {activeMedia.alt_text || 'No alt text added.'}
                                </p>
                            )}
                        </div>

                        {/* Title */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-medium">Title</label>
                            {isEditing ? (
                                <input
                                    type="text"
                                    value={editedTitle}
                                    onChange={(e) => setEditedTitle(e.target.value)}
                                    className="w-full rounded-md border px-3 py-1.5 text-sm"
                                    placeholder="Image title..."
                                />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {activeMedia.name || 'Untitled media item'}
                                </p>
                            )}
                        </div>

                        {/* Caption */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-medium">Caption</label>
                            {isEditing ? (
                                <textarea
                                    value={editedCaption}
                                    onChange={(e) => setEditedCaption(e.target.value)}
                                    className="w-full rounded-md border px-3 py-1.5 text-sm"
                                    rows={3}
                                    placeholder="Optional caption..."
                                />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {activeMedia.caption || 'No caption added.'}
                                </p>
                            )}
                        </div>

                        {/* File URL */}
                        <div className="space-y-1.5 overflow-hidden">
                            <label className="text-xs font-medium">File URL</label>
                            <p
                                className="truncate text-xs text-muted-foreground"
                                title={activeMedia.original_url || ''}
                            >
                                {activeMedia.original_url}
                            </p>
                        </div>
                    </div>
                </ScrollArea>
            )}

            {/* Action Button */}
            {activeMedia && (
                <div className="border-t p-4">
                    {isEditing ? (
                        <Button className="w-full" onClick={onSave}>
                            Save
                        </Button>
                    ) : (
                        <Button className="w-full" onClick={onSelect}>
                            Select
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
