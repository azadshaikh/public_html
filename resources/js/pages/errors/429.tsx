import { TimerReset } from 'lucide-react';
import AuthErrorPage from '@/components/errors/auth-error-page';

type ErrorPageProps = {
    status: number;
    message?: string | null;
};

export default function TooManyRequests({ status, message }: ErrorPageProps) {
    return (
        <AuthErrorPage
            status={status}
            title="Too many requests"
            description="The application needs a moment before it can accept another request."
            defaultMessage="You have made too many requests. Please wait a moment and try again."
            message={message}
            icon={TimerReset}
            showReloadButton
            showBackButton={false}
        />
    );
}
