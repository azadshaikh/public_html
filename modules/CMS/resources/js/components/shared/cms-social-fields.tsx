import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

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
                <Input
                    id="og_image"
                    className={surfaceClassName}
                    type="url"
                    value={ogImage}
                    onChange={(event) => onOgImageChange(event.target.value)}
                    onBlur={onOgImageBlur}
                    aria-invalid={ogImageInvalid || undefined}
                    placeholder="https://example.com/social-image.jpg"
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