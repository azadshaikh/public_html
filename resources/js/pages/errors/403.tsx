import { ShieldX } from 'lucide-react';
import AuthErrorPage from '@/components/errors/auth-error-page';

type ErrorPageProps = {
    status: number;
    message?: string | null;
};

export default function Forbidden({ status, message }: ErrorPageProps) {
    return (
        <AuthErrorPage
            status={status}
            title="Access denied"
            description="Your account does not have permission to open this page."
            defaultMessage="You do not have permission to access this resource."
            message={message}
            icon={ShieldX}
            showLogoutButton
        />
    );
}
