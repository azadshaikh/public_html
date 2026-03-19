import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { CmsPermalinkDisplay } from './cms-permalink-display';

type CmsSlugFieldProps = {
    value: string;
    preSlug: string;
    permalinkPreview: string;
    /** When true, renders a live external link instead of the plain text preview. */
    hasPermalink: boolean;
    onChange: (value: string) => void;
    onTouch: () => void;
    invalid?: boolean;
    error?: string;
};

export function CmsSlugField({
    value,
    preSlug,
    permalinkPreview,
    hasPermalink,
    onChange,
    onTouch,
    invalid,
    error,
}: CmsSlugFieldProps) {
    return (
        <Field data-invalid={invalid || undefined}>
            <FieldLabel htmlFor="slug">Permalink</FieldLabel>
            <div className="flex items-center rounded-lg border bg-muted/20 pl-3">
                <span className="shrink-0 text-sm text-muted-foreground">
                    {preSlug}
                </span>
                <Input
                    id="slug"
                    className="border-0 bg-transparent ring-0 focus-visible:border-0 focus-visible:ring-0"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    onBlur={onTouch}
                    aria-invalid={invalid || undefined}
                    placeholder="auto-generated-from-title"
                />
            </div>
            {hasPermalink ? (
                <CmsPermalinkDisplay href={permalinkPreview} />
            ) : (
                <FieldDescription className="break-all leading-5">
                    {permalinkPreview}
                </FieldDescription>
            )}
            <FieldError>{error}</FieldError>
        </Field>
    );
}
