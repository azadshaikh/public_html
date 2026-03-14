import { Form } from '@inertiajs/react';
import AppHead from '@/components/app-head';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
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
                <div className="rounded-md border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:text-emerald-400">
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

                <Form
                    action={route('verification.send')}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing }) => (
                        <Button
                            className="w-full"
                            disabled={processing}
                            variant="secondary"
                        >
                            {processing && <Spinner />}
                            Resend verification email
                        </Button>
                    )}
                </Form>

                <Form action={route('logout')} method="post" className="w-full">
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="outline"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Log out
                        </Button>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}
