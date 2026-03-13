import { AlertCircleIcon } from 'lucide-react';
import { useMemo } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type FormErrorSummaryProps = {
    errors: Array<string | undefined> | Record<string, string | undefined>;
    title?: string;
};

export function FormErrorSummary({
    errors,
    title,
}: FormErrorSummaryProps) {
    const messages = useMemo(() => {
        const values = Array.isArray(errors) ? errors : Object.values(errors);

        return [
            ...new Set(
                values.filter((value): value is string => Boolean(value)),
            ),
        ];
    }, [errors]);

    if (messages.length === 0) {
        return null;
    }

    const resolvedTitle =
        title ??
        (messages.length === 1
            ? 'Please fix 1 highlighted field.'
            : `Please fix ${messages.length} highlighted fields.`);

    return (
        <Alert variant="destructive" className="py-3">
            <AlertCircleIcon />
            <AlertTitle>{resolvedTitle}</AlertTitle>
            <AlertDescription className="text-sm">
                {messages[0]}
            </AlertDescription>
        </Alert>
    );
}
