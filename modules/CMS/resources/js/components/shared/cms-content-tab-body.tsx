import { AsteroNote } from '@/components/asteronote/asteronote';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';

type CmsContentTabBodyProps = {
    contentValue: string;
    excerptValue: string;
    onContentChange: (value: string) => void;
    onExcerptChange: (value: string) => void;
    onContentBlur?: () => void;
    onExcerptBlur?: () => void;
    contentInvalid?: boolean;
    excerptInvalid?: boolean;
    contentError?: string;
    excerptError?: string;

    /** Optional label shown above the AsteroNote editor. e.g. "Description" for taxonomies. */
    contentLabel?: string;
    contentPlaceholder?: string;
    excerptPlaceholder?: string;

    updatedAtHuman?: string | null;
    updatedAtFormatted?: string | null;

    surfaceClassName?: string;
};

export function CmsContentTabBody({
    contentValue,
    excerptValue,
    onContentChange,
    onExcerptChange,
    onContentBlur,
    onExcerptBlur,
    contentInvalid,
    excerptInvalid,
    contentError,
    excerptError,
    contentLabel,
    contentPlaceholder = 'Write the content here',
    excerptPlaceholder = 'Enter a short excerpt',
    updatedAtHuman,
    updatedAtFormatted,
    surfaceClassName = 'bg-background',
}: CmsContentTabBodyProps) {
    return (
        <>
            <Field data-invalid={contentInvalid || undefined}>
                {contentLabel ? (
                    <FieldLabel htmlFor="content">{contentLabel}</FieldLabel>
                ) : null}
                <AsteroNote
                    id="content"
                    value={contentValue}
                    onChange={onContentChange}
                    onBlur={onContentBlur}
                    placeholder={contentPlaceholder}
                    invalid={contentInvalid || undefined}
                />
                <FieldError>{contentError}</FieldError>
            </Field>

            <Field data-invalid={excerptInvalid || undefined}>
                <FieldLabel htmlFor="excerpt">Excerpt (optional)</FieldLabel>
                <Textarea
                    id="excerpt"
                    className={surfaceClassName}
                    rows={4}
                    value={excerptValue}
                    onChange={(event) => onExcerptChange(event.target.value)}
                    onBlur={onExcerptBlur}
                    aria-invalid={excerptInvalid || undefined}
                    placeholder={excerptPlaceholder}
                />
                <FieldDescription>
                    Used in listings, previews, and search snippets.
                </FieldDescription>
                <FieldError>{excerptError}</FieldError>
            </Field>

            {updatedAtHuman !== undefined ? (
                <div className="text-sm text-muted-foreground">
                    Last updated {updatedAtHuman ?? 'recently'}
                    {updatedAtFormatted ? ` (${updatedAtFormatted})` : ''}
                </div>
            ) : null}
        </>
    );
}
