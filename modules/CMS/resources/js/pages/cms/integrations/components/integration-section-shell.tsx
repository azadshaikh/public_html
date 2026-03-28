import { SaveIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type SectionCardProps = {
    title: string;
    description: string;
    footer?: ReactNode;
    children: ReactNode;
};

type CodeFieldProps = {
    id: string;
    label: string;
    description: string;
    value: string;
    error?: string;
    invalid: boolean;
    onChange: (value: string) => void;
    onBlur: () => void;
    placeholder?: string;
    height?: string;
};

type IntegrationSubmitButtonProps = {
    processing: boolean;
    disabled: boolean;
    label: string;
};

export function IntegrationSectionCard({
    title,
    description,
    footer,
    children,
}: SectionCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                {children}
            </CardContent>
            {footer ? <CardFooter>{footer}</CardFooter> : null}
        </Card>
    );
}

export function IntegrationCodeField({
    id,
    label,
    description,
    value,
    error,
    invalid,
    onChange,
    onBlur,
    placeholder,
    height = '22rem',
}: CodeFieldProps) {
    return (
        <Field data-invalid={invalid || undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <FieldDescription>{description}</FieldDescription>
            <Textarea
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                onBlur={onBlur}
                placeholder={placeholder}
                aria-invalid={invalid || undefined}
                spellCheck={false}
                autoCapitalize="off"
                autoCorrect="off"
                style={{ minHeight: height }}
                className="w-full resize-y font-mono text-sm leading-6"
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export function IntegrationSubmitButton({
    processing,
    disabled,
    label,
}: IntegrationSubmitButtonProps) {
    return (
        <Button type="submit" disabled={processing || disabled}>
            {processing ? (
                <Spinner />
            ) : (
                <SaveIcon data-icon="inline-start" />
            )}
            {label}
        </Button>
    );
}
