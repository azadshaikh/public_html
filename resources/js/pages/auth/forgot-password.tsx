import { Form } from '@inertiajs/react';
import AppHead from '@/components/app-head';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes/index';
import { email } from '@/routes/password';

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
                <div className="rounded-md border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:text-emerald-400">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form
                    {...email.form()}
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
                    <TextLink href={login()}>Back to log in</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
