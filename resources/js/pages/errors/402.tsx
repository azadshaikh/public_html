import { CreditCard } from 'lucide-react';
import AuthErrorPage from '@/components/errors/auth-error-page';

type ErrorPageProps = {
    status: number;
    message?: string | null;
};

export default function PaymentRequired({ status, message }: ErrorPageProps) {
    return (
        <AuthErrorPage
            status={status}
            title="Payment required"
            description="This action needs billing access before it can continue."
            defaultMessage="Payment is required before you can access this resource."
            message={message}
            icon={CreditCard}
        />
    );
}
