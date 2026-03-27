'use client';

import { ImageIcon, Trash2Icon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useCallback, useState } from 'react';

import { MediaPickerDialog } from '@/components/media/media-picker-dialog';
import type { MediaPickerItem } from '@/components/media/media-picker-utils';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';
import { cn } from '@/lib/utils';
import type {
    MediaListItem,
    MediaPickerFilters,
    UploadSettings,
} from '@/types/media';
import type { PaginatedData } from '@/types/pagination';

type MediaPickerUrlInputProps = Omit<
    ComponentProps<typeof InputGroupInput>,
    'value' | 'onChange'
> & {
    value: string;
    onChange: (value: string) => void;
    pickerMedia?: PaginatedData<MediaListItem> | null;
    pickerFilters?: MediaPickerFilters | null;
    uploadSettings?: UploadSettings | null;
    pickerAction: string;
    pickerStatistics?: {
        total: number;
        trash: number;
    } | null;
    dialogTitle?: string;
    pickerButtonLabel?: string;
    clearButtonLabel?: string;
    showThumbnailPreview?: boolean;
    thumbnailAlt?: string;
    containerClassName?: string;
    showClearAction?: boolean;
};

function MediaPickerUrlThumbnailPreview({
    src,
    alt,
    onClear,
    clearButtonLabel,
    showClearAction,
    disabled,
}: {
    src: string;
    alt: string;
    onClear: () => void;
    clearButtonLabel: string;
    showClearAction: boolean;
    disabled: boolean;
}) {
    const [hidden, setHidden] = useState(false);

    if (hidden) {
        return null;
    }

    return (
        <div className="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 p-2.5">
            <img
                src={src}
                alt={alt}
                className="size-12 shrink-0 rounded-md border bg-background object-cover"
                onError={() => setHidden(true)}
            />
            <div className="min-w-0 flex-1">
                <p className="text-[11px] font-medium uppercase tracking-[0.12em] text-muted-foreground">
                    Selected image
                </p>
                <p className="truncate text-xs text-muted-foreground">{src}</p>
            </div>
            {showClearAction ? (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onClear}
                    aria-label={clearButtonLabel}
                    disabled={disabled}
                >
                    <Trash2Icon data-icon="inline-start" />
                    Remove
                </Button>
            ) : null}
        </div>
    );
}

export function MediaPickerUrlInput({
    value,
    onChange,
    pickerMedia = null,
    pickerFilters = null,
    uploadSettings = null,
    pickerAction,
    pickerStatistics = null,
    dialogTitle = 'Select Image',
    pickerButtonLabel = 'Open media picker',
    clearButtonLabel = 'Clear media URL',
    showThumbnailPreview = false,
    thumbnailAlt = 'Selected media preview',
    containerClassName,
    showClearAction = true,
    disabled = false,
    ...inputProps
}: MediaPickerUrlInputProps) {
    const [open, setOpen] = useState(false);

    const handleSelect = useCallback(
        (items: MediaPickerItem[]) => {
            const item = items[0];

            if (!item) {
                return;
            }

            onChange(
                item.media_url || item.original_url || item.thumbnail_url || '',
            );
            setOpen(false);
        },
        [onChange],
    );

    const handleClear = useCallback(() => {
        onChange('');
    }, [onChange]);

    return (
        <>
            <div className="flex flex-col gap-2">
                <InputGroup
                    size="comfortable"
                    className={cn(containerClassName)}
                >
                    <InputGroupInput
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        disabled={disabled}
                        {...inputProps}
                    />

                    <InputGroupAddon align="inline-end">
                        <InputGroupButton
                            aria-label={pickerButtonLabel}
                            size="icon-sm"
                            onClick={() => setOpen(true)}
                            disabled={disabled}
                        >
                            <ImageIcon />
                        </InputGroupButton>
                    </InputGroupAddon>
                </InputGroup>

                {showThumbnailPreview && value !== '' && showClearAction ? (
                    <MediaPickerUrlThumbnailPreview
                        key={value}
                        src={value}
                        alt={thumbnailAlt}
                        onClear={handleClear}
                        clearButtonLabel={clearButtonLabel}
                        showClearAction
                        disabled={disabled}
                    />
                ) : showThumbnailPreview && value !== '' ? (
                    <MediaPickerUrlThumbnailPreview
                        key={value}
                        src={value}
                        alt={thumbnailAlt}
                        onClear={() => undefined}
                        clearButtonLabel={clearButtonLabel}
                        showClearAction={false}
                        disabled
                    />
                ) : null}
            </div>

            <MediaPickerDialog
                open={open}
                onOpenChange={setOpen}
                onSelect={handleSelect}
                selection="single"
                title={dialogTitle}
                pickerMedia={pickerMedia}
                pickerFilters={pickerFilters}
                uploadSettings={uploadSettings}
                pickerAction={pickerAction}
                pickerStatistics={pickerStatistics}
            />
        </>
    );
}