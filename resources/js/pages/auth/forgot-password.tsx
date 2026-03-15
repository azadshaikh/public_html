import { Form } from '@inertiajs/react';
import AppHead from '@/components/app-head';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Forgot password"
            description="Enter your email address and we will send you a reset link."
        >
            <AppHead
                title="Forgot password"
                description="Request a password reset link for your account."
            />

            {status && (
                <div className="rounded-md border border-[var(--success-border)] bg-[var(--success-bg)] px-4 py-3 text-center text-sm font-medium text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form
                    action={route('password.email')}
                    method="post"
                    disableWhileProcessing
                    className="flex flex-col gap-6 inert:pointer-events-none inert:opacity-60"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    autoFocus
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                Email password reset link
                            </Button>
                        </>
                    )}
                </Form>

                <div className="text-center text-sm text-muted-foreground">
                    <span>Remembered your password? </span>
                    <TextLink href={route('login')}>Back to log in</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
