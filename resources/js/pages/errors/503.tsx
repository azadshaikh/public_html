import { Wrench } from 'lucide-react';
import AuthErrorPage from '@/components/errors/auth-error-page';

type ErrorPageProps = {
    status: number;
    message?: string | null;
};

export default function ServiceUnavailable({ status, message }: ErrorPageProps) {
    return (
        <AuthErrorPage
            status={status}
            title="Service unavailable"
            description="The application is temporarily unavailable or under maintenance."
            defaultMessage="We are currently performing maintenance. Please check back shortly."
            message={message}
            icon={Wrench}
            showReloadButton
            showBackButton={false}
            accentClassName="bg-amber-500"
            iconClassName="border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
        />
    );
}