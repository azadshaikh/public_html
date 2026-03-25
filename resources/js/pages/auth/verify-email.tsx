import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AuthLayout from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const resendForm = useAppForm({
        defaults: {},
        rememberKey: 'auth.verify-email.resend',
    });

    const logoutForm = useAppForm({
        defaults: {},
        rememberKey: 'auth.verify-email.logout',
    });

    const handleResend = (e: FormEvent) => {
        e.preventDefault();
        resendForm.submit('post', route('verification.send'));
    };

    const handleLogout = (e: FormEvent) => {
        e.preventDefault();
        logoutForm.submit('post', route('logout'));
    };

    return (
        <AuthLayout
            title="Verify email"
            description="Please verify your email address by clicking on the link we just emailed to you."
        >
            <AppHead
                title="Email verification"
                description="Verify your email address to finish setting up your account."
            />

            {status === 'verification-required' && (
                <div className="rounded-md border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-center text-sm font-medium text-amber-700 dark:text-amber-400">
                    Please verify your email address before continuing.
                </div>
            )}

            {status === 'verification-link-sent' && (
                <div className="rounded-md border border-[var(--success-border)] bg-[var(--success-bg)] px-4 py-3 text-center text-sm font-medium text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <div className="space-y-6">
                <p className="text-center text-sm leading-6 text-muted-foreground">
                    Check your inbox and click the verification link we emailed
                    to you. If you did not receive the message, you can request
                    a new one below.
                </p>

                <form onSubmit={handleResend} className="space-y-4">
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={resendForm.processing}
                        variant="secondary"
                        size="xl"
                    >
                        {resendForm.processing && (
                            <Spinner className="mr-2 h-4 w-4" />
                        )}
                        Resend verification email
                    </Button>
                </form>

                <form onSubmit={handleLogout} className="w-full">
                    <Button
                        type="submit"
                        variant="outline"
                        className="w-full"
                        disabled={logoutForm.processing}
                        size="xl"
                    >
                        {logoutForm.processing && (
                            <Spinner className="mr-2 h-4 w-4" />
                        )}
                        Log out
                    </Button>
                </form>
            </div>
        </AuthLayout>
    );
}
