import { SearchX } from 'lucide-react';
import AuthErrorPage from '@/components/errors/auth-error-page';

type ErrorPageProps = {
    status: number;
    message?: string | null;
};

export default function NotFound({ status, message }: ErrorPageProps) {
    return (
        <AuthErrorPage
            status={status}
            title="Page not found"
            description="The page you requested could not be found."
            defaultMessage="The page may have been moved, deleted, or may never have existed."
            message={message}
            icon={SearchX}
        />
    );
}