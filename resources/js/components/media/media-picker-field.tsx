'use client';

import { ImageIcon, ImagePlusIcon, Trash2Icon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { MediaPickerDialog } from '@/components/media/media-picker-dialog';
import type { MediaPickerItem } from '@/components/media/media-picker-utils';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { MediaListItem, MediaPickerFilters, UploadSettings } from '@/types/media';
import type { PaginatedData } from '@/types/pagination';

// ─── Types ───────────────────────────────────────────────────────────────

type MediaPickerFieldProps = {
    /** Current media ID value (from form). */
    value: number | '' | null;
    /** Called when a media item is selected. Returns the selected item. */
    onChange: (item: MediaPickerItem | null) => void;
    /** Pre-resolved preview URL for the current value (optional). */
    previewUrl?: string | null;
    /** Width classes for the field container. */
    className?: string;
    /** Height of the preview area. */
    previewHeight?: string;
    /** Dialog title. */
    dialogTitle?: string;
    /** Whether the field is disabled. */
    disabled?: boolean;
    /** The label for the select button shown in the empty state. */
    selectLabel?: string;
    /** A11y label. */
    'aria-label'?: string;
    /** A11y invalid state. */
    'aria-invalid'?: boolean;
    /** Inertia-backed paginated media data (null on first load). */
    pickerMedia?: PaginatedData<MediaListItem> | null;
    /** Current filter state from the server (null on first load). */
    pickerFilters?: MediaPickerFilters | null;
    /** Upload settings from the server. */
    uploadSettings?: UploadSettings | null;
    /** Inertia action URL the Datagrid submits to (current page URL). */
    pickerAction?: string;
    /** Statistics for the tabs. */
    pickerStatistics?: {
        total: number;
        trash: number;
    } | null;
};

// ─── Component ───────────────────────────────────────────────────────────

export function MediaPickerField({
    value,
    onChange,
    previewUrl: externalPreviewUrl,
    className,
    previewHeight = 'aspect-video',
    dialogTitle = 'Select Image',
    disabled = false,
    selectLabel = 'Select Image',
    'aria-label': ariaLabel,
    'aria-invalid': ariaInvalid,
    pickerMedia = null,
    pickerFilters = null,
    uploadSettings = null,
    pickerAction = '',
    pickerStatistics = null,
}: MediaPickerFieldProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    // Track the preview URL that was resolved from the media picker selection
    const [pickerPreview, setPickerPreview] = useState<string | null>(null);
    // Track async fetch state for previewing a value set externally
    const [fetchedPreview, setFetchedPreview] = useState<string | null>(null);
    const [loadingPreview, setLoadingPreview] = useState(false);
    const lastFetchedValueRef = useRef<number | null>(null);

    // Resolve the final preview URL:
    // 1. pickerPreview (just selected from picker) takes priority
    // 2. externalPreviewUrl (from server props) is next
    // 3. fetchedPreview (fetched for an id-only value) is last
    const resolvedPreview = pickerPreview ?? externalPreviewUrl ?? fetchedPreview ?? null;

    // When value is set externally with no preview URL available,
    // fetch it. The async fetch callback is fine within useEffect.
    useEffect(() => {
        if (
            !value ||
            typeof value !== 'number' ||
            pickerPreview ||
            externalPreviewUrl
        ) {
            return;
        }

        // Skip if we already fetched this value
        if (lastFetchedValueRef.current === value && fetchedPreview) {
            return;
        }

        lastFetchedValueRef.current = value;
        let cancelled = false;

        (async () => {
            setLoadingPreview(true);
            try {
                const res = await fetch(route('app.media.details', value), {
                    headers: { Accept: 'application/json' },
                });
                const json = await res.json();
                if (!cancelled && json.status === 1 && json.data) {
                    setFetchedPreview(
                        json.data.thumbnail_url ||
                        json.data.media_url ||
                        json.data.original_url,
                    );
                }
            } catch {
                // silently fail
            } finally {
                if (!cancelled) {
                    setLoadingPreview(false);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [value, pickerPreview, externalPreviewUrl, fetchedPreview]);

    const handleSelect = useCallback(
        (items: MediaPickerItem[]) => {
            const item = items[0];
            if (item) {
                setPickerPreview(
                    item.thumbnail_url || item.media_url || item.original_url,
                );
                setFetchedPreview(null);
                onChange(item);
            }
        },
        [onChange],
    );

    const handleClear = useCallback(() => {
        setPickerPreview(null);
        setFetchedPreview(null);
        lastFetchedValueRef.current = null;
        onChange(null);
    }, [onChange]);

    const hasValue = value !== '' && value !== null && value !== undefined;

    return (
        <>
            <div
                className={cn('group relative', className)}
                aria-label={ariaLabel}
                aria-invalid={ariaInvalid}
            >
                {hasValue && resolvedPreview ? (
                    /* ── Preview state ──────────────────────── */
                    <div className={cn('relative overflow-hidden rounded-lg border bg-muted/30', previewHeight)}>
                        <img
                            src={resolvedPreview}
                            alt="Selected media"
                            className="size-full object-cover"
                        />

                        {/* Overlay actions */}
                        <div className="absolute inset-0 flex items-center justify-center gap-2 bg-black/0 opacity-0 transition-all group-hover:bg-black/30 group-hover:opacity-100">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => setDialogOpen(true)}
                                disabled={disabled}
                            >
                                <ImageIcon data-icon="inline-start" />
                                Change
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                className="bg-destructive text-white hover:bg-destructive/90 hover:text-white [&_svg]:text-white"
                                onClick={handleClear}
                                disabled={disabled}
                            >
                                <Trash2Icon data-icon="inline-start" />
                                Remove
                            </Button>
                        </div>
                    </div>
                ) : hasValue && loadingPreview ? (
                    /* ── Loading state ─────────────────────── */
                    <div
                        className={cn(
                            'flex items-center justify-center rounded-lg border border-dashed bg-muted/30',
                            previewHeight,
                        )}
                    >
                        <div className="flex flex-col items-center gap-2 text-muted-foreground">
                            <div className="size-5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                            <span className="text-xs">Loading preview…</span>
                        </div>
                    </div>
                ) : (
                    /* ── Empty state ───────────────────────── */
                    <button
                        type="button"
                        onClick={() => setDialogOpen(true)}
                        disabled={disabled}
                        className={cn(
                            'flex w-full cursor-pointer items-center justify-center rounded-lg border-2 border-dashed transition-colors',
                            'hover:border-primary/50 hover:bg-muted/50',
                            'disabled:cursor-not-allowed disabled:opacity-50',
                            previewHeight,
                        )}
                    >
                        <div className="flex flex-col items-center gap-2 text-muted-foreground">
                            <ImagePlusIcon className="size-8 opacity-40" />
                            <span className="text-sm font-medium">
                                {selectLabel}
                            </span>
                        </div>
                    </button>
                )}
            </div>

            <MediaPickerDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                onSelect={handleSelect}
                selection="single"
                title={dialogTitle}
                pickerMedia={pickerMedia}
                pickerFilters={pickerFilters}
                uploadSettings={uploadSettings}
                pickerAction={pickerAction}
                pickerStatistics={pickerStatistics}
                initialSelectedId={typeof value === 'number' ? value : null}
            />
        </>
    );
}
