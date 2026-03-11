import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type ResourceFeedbackAlertsProps = {
    status?: string | null;
    statusTitle?: string;
    statusIcon: ReactNode;
    error?: string | null;
    errorTitle?: string;
    errorIcon: ReactNode;
    errorClassName?: string;
};

export function ResourceFeedbackAlerts({
    status,
    statusTitle = 'Saved',
    statusIcon,
    error,
    errorTitle = 'Action blocked',
    errorIcon,
    errorClassName = 'border-destructive/30 text-destructive dark:border-destructive/40',
}: ResourceFeedbackAlertsProps) {
    return (
        <>
            {status ? (
                <Alert>
                    {statusIcon}
                    <AlertTitle>{statusTitle}</AlertTitle>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            ) : null}

            {error ? (
                <Alert className={errorClassName}>
                    {errorIcon}
                    <AlertTitle>{errorTitle}</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            ) : null}
        </>
    );
}
