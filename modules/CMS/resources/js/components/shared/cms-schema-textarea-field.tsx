import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';

type CmsSchemaTextareaFieldProps = {
    value: string;
    onChange: (value: string) => void;
    onBlur: () => void;
    invalid?: boolean;
    error?: string;
    className?: string;
    rows?: number;
};

export function CmsSchemaTextareaField({
    value,
    onChange,
    onBlur,
    invalid = false,
    error,
    className,
    rows = 12,
}: CmsSchemaTextareaFieldProps) {
    return (
        <Field data-invalid={invalid || undefined}>
            <FieldLabel htmlFor="schema">Schema markup</FieldLabel>
            <Textarea
                id="schema"
                className={className}
                rows={rows}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                onBlur={onBlur}
                aria-invalid={invalid || undefined}
                placeholder="Add custom schema markup (JSON-LD or other structured data)"
            />
            <FieldDescription>
                Optional structured data for search engines.
            </FieldDescription>
            <FieldError>{error}</FieldError>
        </Field>
    );
}