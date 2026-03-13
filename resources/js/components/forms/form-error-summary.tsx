import { AlertCircleIcon } from 'lucide-react';
import { useMemo } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { normalizeFormErrorMessage } from '@/lib/forms';

type FormErrorSummaryProps = {
    errors: Array<string | undefined> | Record<string, string | undefined>;
    title?: string;
    enabled?: boolean;
    minMessages?: number;
};

export function FormErrorSummary({
    errors,
    title,
    enabled = true,
    minMessages = 1,
}: FormErrorSummaryProps) {
    const messages = useMemo(() => {
        const values = Array.isArray(errors) ? errors : Object.values(errors);

        return [
            ...new Set(
                values
                    .map((value) =>
                        normalizeFormErrorMessage(
                            typeof value === 'string' ? value : undefined,
                        ),
                    )
                    .filter((value): value is string => Boolean(value)),
            ),
        ];
    }, [errors]);

    if (!enabled || messages.length < minMessages) {
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
