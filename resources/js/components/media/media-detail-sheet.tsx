import {
    AlertCircleIcon,
    CheckCircleIcon,
    CopyIcon,
    FileIcon,
    LoaderIcon,
    SaveIcon,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { MediaDetail } from '@/types/media';

type MediaDetailSheetProps = {
    mediaId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onUpdated?: () => void;
};

type ConversionStatusMap = Record<string, boolean>;

export function MediaDetailSheet({
    mediaId,
    open,
    onOpenChange,
    onUpdated,
}: MediaDetailSheetProps) {
    const [detail, setDetail] = useState<MediaDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [conversionPolling, setConversionPolling] = useState(false);
    const [copyFeedback, setCopyFeedback] = useState<string | null>(null);

    // Editable fields
    const [name, setName] = useState('');
    const [altText, setAltText] = useState('');
    const [caption, setCaption] = useState('');
    const [description, setDescription] = useState('');
    const [tags, setTags] = useState('');

    // ── Fetch details ─────────────────────────────────────────────

    const fetchDetails = useCallback(async (id: number) => {
        setLoading(true);
        try {
            const res = await fetch(route('app.media.details', id), {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();
            if (json.status === 1 && json.data) {
                const d = json.data as MediaDetail;
                setDetail(d);
                setName(d.name);
                setAltText(d.alt_text);
                setCaption(d.caption);
                setDescription(d.description);
                setTags(d.tags);
            }
        } catch {
            // silently fail
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (open && mediaId) {
            fetchDetails(mediaId);
        }
        if (!open) {
            setDetail(null);
            setConversionPolling(false);
        }
    }, [open, mediaId, fetchDetails]);

    // ── Poll for conversion status ──────────────────────────────

    useEffect(() => {
        if (!detail || !detail.is_processing || !open) return;

        const detailId = detail.id;
        setConversionPolling(true);
        const interval = setInterval(async () => {
            try {
                const res = await fetch(
                    route('app.media.conversion-status', detailId),
                    {
                        headers: { Accept: 'application/json' },
                    },
                );
                const json = await res.json();
                const convStatus = json.data?.conversion_status;
                if (convStatus?.status === 'completed') {
                    setDetail((prev) =>
                        prev
                            ? {
                                  ...prev,
                                  is_processing: false,
                                  conversion_status: convStatus,
                              }
                            : prev,
                    );
                    setConversionPolling(false);
                    clearInterval(interval);
                } else if (convStatus) {
                    // Update conversion progress even if not yet completed
                    setDetail((prev) =>
                        prev
                            ? {
                                  ...prev,
                                  conversion_status: convStatus,
                              }
                            : prev,
                    );
                }
            } catch {
                // ignore polling errors
            }
        }, 3000);

        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [detail?.id, detail?.is_processing, open]);

    // ── Save metadata ───────────────────────────────────────────

    const handleSave = useCallback(async () => {
        if (!detail) return;

        setSaving(true);
        try {
            const csrfMeta = document.head.querySelector(
                'meta[name="csrf-token"]',
            );
            const csrfToken = csrfMeta?.getAttribute('content') ?? '';

            const formData = new FormData();
            formData.append('media_id', String(detail.id));
            formData.append('media_name', name);
            formData.append('media_alt', altText);
            formData.append('media_caption', caption);
            formData.append('media_description', description);
            formData.append('media_tags', tags);

            const res = await fetch(route('app.media.detail.update'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: formData,
            });
            const json = await res.json();
            if (json.status) {
                onUpdated?.();
            }
        } catch {
            // silently fail
        } finally {
            setSaving(false);
        }
    }, [detail, name, altText, caption, description, tags, onUpdated]);

    // ── Copy URL ────────────────────────────────────────────────

    const copyUrl = useCallback((url: string, label: string) => {
        navigator.clipboard.writeText(url).then(() => {
            setCopyFeedback(label);
            setTimeout(() => setCopyFeedback(null), 2000);
        });
    }, []);

    // ── Render ──────────────────────────────────────────────────

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-full overflow-y-auto sm:max-w-lg"
            >
                <SheetHeader>
                    <SheetTitle>Media Details</SheetTitle>
                    <SheetDescription>
                        View and edit file information
                    </SheetDescription>
                </SheetHeader>

                {loading && (
                    <div className="flex items-center justify-center py-12">
                        <LoaderIcon className="size-6 animate-spin text-muted-foreground" />
                    </div>
                )}

                {!loading && detail && (
                    <div className="flex flex-col gap-6 px-4 pb-6">
                        {/* Preview */}
                        <div className="relative overflow-hidden rounded-lg border bg-muted">
                            {detail.mime_type.startsWith('image/') ? (
                                <img
                                    src={
                                        detail.media_url || detail.original_url
                                    }
                                    alt={detail.alt_text || detail.name}
                                    className="max-h-64 w-full object-contain"
                                />
                            ) : (
                                <div className="flex items-center justify-center py-12">
                                    <FileIcon className="size-16 text-muted-foreground/50" />
                                </div>
                            )}
                        </div>

                        {/* Conversion Status */}
                        <ConversionStatusSection
                            isProcessing={detail.is_processing}
                            processingFailed={detail.processing_failed}
                            processingError={detail.processing_error}
                            conversionStatus={detail.conversion_status}
                            polling={conversionPolling}
                        />

                        {/* File Info */}
                        <div className="grid grid-cols-2 gap-3">
                            <InfoField
                                label="File Name"
                                value={detail.file_name}
                            />
                            <InfoField label="Type" value={detail.mime_type} />
                            <InfoField
                                label="Size"
                                value={detail.human_readable_size}
                            />
                            {detail.width > 0 && detail.height > 0 && (
                                <InfoField
                                    label="Dimensions"
                                    value={`${detail.width} × ${detail.height}px`}
                                />
                            )}
                            <InfoField
                                label="Uploaded"
                                value={
                                    detail.created_at
                                        ? new Date(
                                              detail.created_at,
                                          ).toLocaleDateString('en-US', {
                                              month: 'short',
                                              day: 'numeric',
                                              year: 'numeric',
                                          })
                                        : '—'
                                }
                            />
                            {detail.owner && (
                                <InfoField
                                    label="Owner"
                                    value={detail.owner.name}
                                />
                            )}
                        </div>

                        {/* URLs */}
                        {detail.original_url && (
                            <>
                                <Separator />
                                <div className="space-y-2">
                                    <h4 className="text-sm font-medium text-foreground">
                                        URLs
                                    </h4>
                                    <UrlCopyRow
                                        label="Original"
                                        url={detail.original_url}
                                        onCopy={copyUrl}
                                        feedback={copyFeedback}
                                    />
                                    {detail.media_url &&
                                        detail.media_url !==
                                            detail.original_url && (
                                            <UrlCopyRow
                                                label="Optimized"
                                                url={detail.media_url}
                                                onCopy={copyUrl}
                                                feedback={copyFeedback}
                                            />
                                        )}
                                    {detail.thumbnail_url && (
                                        <UrlCopyRow
                                            label="Thumbnail"
                                            url={detail.thumbnail_url}
                                            onCopy={copyUrl}
                                            feedback={copyFeedback}
                                        />
                                    )}
                                </div>
                            </>
                        )}

                        {/* Variations */}
                        {detail.variations &&
                            Object.keys(detail.variations).length > 0 && (
                                <>
                                    <Separator />
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-medium text-foreground">
                                            Variations
                                        </h4>
                                        <div className="flex flex-wrap gap-1.5">
                                            {Object.keys(detail.variations).map(
                                                (key) => (
                                                    <Badge
                                                        key={key}
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        {key}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                </>
                            )}

                        {/* Editable Metadata */}
                        <Separator />
                        <div className="space-y-4">
                            <h4 className="text-sm font-medium text-foreground">
                                Metadata
                            </h4>

                            <div className="space-y-2">
                                <Label htmlFor="media-name">Name</Label>
                                <Input
                                    id="media-name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="media-alt">Alt Text</Label>
                                <Input
                                    id="media-alt"
                                    value={altText}
                                    onChange={(e) => setAltText(e.target.value)}
                                    placeholder="Describe this image for accessibility"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="media-caption">Caption</Label>
                                <Input
                                    id="media-caption"
                                    value={caption}
                                    onChange={(e) => setCaption(e.target.value)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="media-description">
                                    Description
                                </Label>
                                <Textarea
                                    id="media-description"
                                    value={description}
                                    onChange={(e) =>
                                        setDescription(e.target.value)
                                    }
                                    rows={3}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="media-tags">Tags</Label>
                                <Input
                                    id="media-tags"
                                    value={tags}
                                    onChange={(e) => setTags(e.target.value)}
                                    placeholder="Comma-separated tags"
                                />
                            </div>

                            <Button
                                onClick={handleSave}
                                disabled={saving}
                                className="w-full gap-2"
                            >
                                {saving ? (
                                    <LoaderIcon className="size-4 animate-spin" />
                                ) : (
                                    <SaveIcon className="size-4" />
                                )}
                                {saving ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

// =========================================================================
// SUB-COMPONENTS
// =========================================================================

function ConversionStatusSection({
    isProcessing,
    processingFailed,
    processingError,
    conversionStatus,
    polling,
}: {
    isProcessing: boolean;
    processingFailed: boolean;
    processingError: string | null;
    conversionStatus: MediaDetail['conversion_status'];
    polling: boolean;
}) {
    if (
        !isProcessing &&
        !processingFailed &&
        conversionStatus.status === 'completed'
    ) {
        return null;
    }

    const conversions = (conversionStatus.conversions ??
        {}) as ConversionStatusMap;
    const conversionEntries = Object.entries(conversions);

    return (
        <div className="space-y-3 rounded-lg border p-4">
            <div className="flex items-center gap-2">
                {isProcessing ? (
                    <>
                        <LoaderIcon className="size-4 animate-spin text-primary" />
                        <span className="text-sm font-medium text-primary">
                            Optimizing{polling ? '...' : ''}
                        </span>
                    </>
                ) : processingFailed ? (
                    <>
                        <AlertCircleIcon className="size-4 text-destructive" />
                        <span className="text-sm font-medium text-destructive">
                            Optimization Failed
                        </span>
                    </>
                ) : (
                    <>
                        <CheckCircleIcon className="size-4 text-green-500" />
                        <span className="text-sm font-medium text-green-600 dark:text-green-400">
                            Optimization Complete
                        </span>
                    </>
                )}
            </div>

            {processingError && (
                <p className="text-xs text-destructive">{processingError}</p>
            )}

            {conversionEntries.length > 0 && (
                <div className="space-y-1.5">
                    {conversionEntries.map(([key, done]) => (
                        <div key={key} className="flex items-center gap-2">
                            {done ? (
                                <CheckCircleIcon className="size-3.5 text-green-500" />
                            ) : isProcessing ? (
                                <LoaderIcon className="size-3.5 animate-spin text-muted-foreground" />
                            ) : (
                                <AlertCircleIcon className="size-3.5 text-muted-foreground" />
                            )}
                            <span className="text-xs text-muted-foreground capitalize">
                                {key.replace(/_/g, ' ')}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function InfoField({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border bg-muted/30 px-3 py-2">
            <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-0.5 truncate text-sm text-foreground">
                {value}
            </div>
        </div>
    );
}

function UrlCopyRow({
    label,
    url,
    onCopy,
    feedback,
}: {
    label: string;
    url: string;
    onCopy: (url: string, label: string) => void;
    feedback: string | null;
}) {
    return (
        <div className="flex items-center gap-2">
            <span className="w-20 shrink-0 text-xs font-medium text-muted-foreground">
                {label}
            </span>
            <code className="min-w-0 flex-1 truncate rounded bg-muted px-2 py-1 text-xs">
                {url}
            </code>
            <Button
                variant="ghost"
                size="icon-sm"
                onClick={() => onCopy(url, label)}
                title={`Copy ${label} URL`}
            >
                {feedback === label ? (
                    <CheckCircleIcon className="size-3.5 text-green-500" />
                ) : (
                    <CopyIcon className="size-3.5" />
                )}
            </Button>
        </div>
    );
}
