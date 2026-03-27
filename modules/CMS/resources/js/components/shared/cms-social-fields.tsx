import { MediaPickerUrlInput } from '@/components/media/media-picker-url-input';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type {
    MediaListItem,
    MediaPickerFilters,
    UploadSettings,
} from '@/types/media';
import type { PaginatedData } from '@/types/pagination';

type CmsSocialFieldsProps = {
    ogTitle: string;
    ogDescription: string;
    ogImage: string;
    ogUrl: string;
    onOgTitleChange: (value: string) => void;
    onOgDescriptionChange: (value: string) => void;
    onOgImageChange: (value: string) => void;
    onOgUrlChange: (value: string) => void;
    onOgTitleBlur: () => void;
    onOgDescriptionBlur: () => void;
    onOgImageBlur: () => void;
    onOgUrlBlur: () => void;
    ogTitleInvalid?: boolean;
    ogDescriptionInvalid?: boolean;
    ogImageInvalid?: boolean;
    ogUrlInvalid?: boolean;
    ogTitleError?: string;
    ogDescriptionError?: string;
    ogImageError?: string;
    ogUrlError?: string;
    surfaceClassName?: string;
    ogUrlPlaceholder?: string;
    pickerMedia?: PaginatedData<MediaListItem> | null;
    pickerFilters?: MediaPickerFilters | null;
    uploadSettings?: UploadSettings | null;
    pickerStatistics?: {
        total: number;
        trash: number;
    } | null;
    pickerAction?: string;
};

export function CmsSocialFields({
    ogTitle,
    ogDescription,
    ogImage,
    ogUrl,
    onOgTitleChange,
    onOgDescriptionChange,
    onOgImageChange,
    onOgUrlChange,
    onOgTitleBlur,
    onOgDescriptionBlur,
    onOgImageBlur,
    onOgUrlBlur,
    ogTitleInvalid = false,
    ogDescriptionInvalid = false,
    ogImageInvalid = false,
    ogUrlInvalid = false,
    ogTitleError,
    ogDescriptionError,
    ogImageError,
    ogUrlError,
    surfaceClassName,
    ogUrlPlaceholder = 'https://example.com/your-page',
    pickerMedia = null,
    pickerFilters = null,
    uploadSettings = null,
    pickerStatistics = null,
    pickerAction = '',
}: CmsSocialFieldsProps) {
    return (
        <>
            <Field data-invalid={ogTitleInvalid || undefined}>
                <FieldLabel htmlFor="og_title">Open Graph title</FieldLabel>
                <Input
                    id="og_title"
                    className={surfaceClassName}
                    value={ogTitle}
                    onChange={(event) => onOgTitleChange(event.target.value)}
                    onBlur={onOgTitleBlur}
                    aria-invalid={ogTitleInvalid || undefined}
                    placeholder="Enter Open Graph title"
                />
                <FieldError>{ogTitleError}</FieldError>
            </Field>

            <Field data-invalid={ogDescriptionInvalid || undefined}>
                <FieldLabel htmlFor="og_description">
                    Open Graph description
                </FieldLabel>
                <Textarea
                    id="og_description"
                    className={surfaceClassName}
                    rows={4}
                    value={ogDescription}
                    onChange={(event) =>
                        onOgDescriptionChange(event.target.value)
                    }
                    onBlur={onOgDescriptionBlur}
                    aria-invalid={ogDescriptionInvalid || undefined}
                    placeholder="Enter Open Graph description"
                />
                <FieldError>{ogDescriptionError}</FieldError>
            </Field>

            <Field data-invalid={ogImageInvalid || undefined}>
                <FieldLabel htmlFor="og_image">Open Graph image</FieldLabel>
                <MediaPickerUrlInput
                    id="og_image"
                    type="url"
                    value={ogImage}
                    onChange={onOgImageChange}
                    onBlur={onOgImageBlur}
                    aria-invalid={ogImageInvalid || undefined}
                    placeholder="https://example.com/social-image.jpg"
                    containerClassName={surfaceClassName}
                    pickerMedia={pickerMedia}
                    pickerFilters={pickerFilters}
                    uploadSettings={uploadSettings}
                    pickerStatistics={pickerStatistics}
                    pickerAction={pickerAction}
                    dialogTitle="Select Open Graph image"
                    pickerButtonLabel="Select Open Graph image from media library"
                    clearButtonLabel="Clear Open Graph image"
                    showThumbnailPreview
                    thumbnailAlt="Open Graph image preview"
                />
                <FieldDescription>
                    Paste an image URL or choose one from the media library.
                </FieldDescription>
                <FieldError>{ogImageError}</FieldError>
            </Field>

            <Field data-invalid={ogUrlInvalid || undefined}>
                <FieldLabel htmlFor="og_url">Open Graph URL</FieldLabel>
                <Input
                    id="og_url"
                    className={surfaceClassName}
                    type="url"
                    value={ogUrl}
                    onChange={(event) => onOgUrlChange(event.target.value)}
                    onBlur={onOgUrlBlur}
                    aria-invalid={ogUrlInvalid || undefined}
                    placeholder={ogUrlPlaceholder}
                />
                <FieldError>{ogUrlError}</FieldError>
            </Field>
        </>
    );
}