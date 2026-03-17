import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AuthLayout from '@/layouts/auth-layout';

export default function ForgotPassword({ status }: { status?: string }) {
    const form = useAppForm({
        defaults: {
            email: '',
        },
        rememberKey: 'auth.forgot-password',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('password.email'));
    };

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
                <form
                    onSubmit={submit}
                    noValidate
                    className="flex flex-col gap-6 inert:pointer-events-none inert:opacity-60"
                >
                    <Field data-invalid={form.invalid('email') || undefined}>
                        <FieldLabel htmlFor="email">Email address</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            size="xl"
                            autoComplete="email"
                            autoFocus
                            placeholder="email@example.com"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setField('email', e.target.value)
                            }
                            onBlur={() => form.touch('email')}
                            aria-invalid={form.invalid('email') || undefined}
                        />
                        <FieldError>{form.error('email')}</FieldError>
                    </Field>

                    <Button
                        type="submit"
                        size="xl"
                        className="w-full"
                        disabled={form.processing}
                        data-test="email-password-reset-link-button"
                    >
                        {form.processing && <Spinner />}
                        Email password reset link
                    </Button>
                </form>

                <div className="text-center text-sm text-muted-foreground">
                    <span>Remembered your password? </span>
                    <TextLink href={route('login')}>Back to log in</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
