import { ShieldAlert } from 'lucide-react';
import AuthErrorPage from '@/components/errors/auth-error-page';

type ErrorPageProps = {
    status: number;
    message?: string | null;
};

export default function Unauthorized({ status, message }: ErrorPageProps) {
    return (
        <AuthErrorPage
            status={status}
            title="Unauthorized"
            description="You need to sign in with an account that can access this resource."
            defaultMessage="Your account is not authorized for this request. Please sign in and try again."
            message={message}
            icon={ShieldAlert}
            showBackButton={false}
        />
    );
}