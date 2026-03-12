import { ServerCrash } from 'lucide-react';
import AuthErrorPage from '@/components/errors/auth-error-page';

type ErrorPageProps = {
    status: number;
};

export default function InternalServerError({ status }: ErrorPageProps) {
    return (
        <AuthErrorPage
            status={status}
            title="Internal server error"
            description="Something unexpected happened while processing your request."
            defaultMessage="An unexpected server error occurred. Please try again in a moment."
            icon={ServerCrash}
            showReloadButton
            showBackButton={false}
            accentClassName="bg-destructive"
            iconClassName="border-destructive/20 bg-destructive/10 text-destructive"
        />
    );
}