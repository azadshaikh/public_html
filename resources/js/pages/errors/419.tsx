import { Clock3 } from 'lucide-react';
import AuthErrorPage from '@/components/errors/auth-error-page';

type ErrorPageProps = {
    status: number;
    message?: string | null;
};

export default function PageExpired({ status, message }: ErrorPageProps) {
    return (
        <AuthErrorPage
            status={status}
            title="Page expired"
            description="Your session or form state is no longer valid."
            defaultMessage="Your session expired before the request completed. Refresh and try again."
            message={message}
            icon={Clock3}
            showReloadButton
            showBackButton={false}
        />
    );
}
