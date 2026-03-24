import { Link } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AgencyAuthLayout from '../../../components/agency-auth-layout';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    socialProviders: {
        google: boolean;
        github: boolean;
    };
};

export default function AgencySignIn({
    status,
    canResetPassword,
    canRegister,
    socialProviders,
}: Props) {
    const [loadingProvider, setLoadingProvider] = useState<
        null | 'google' | 'github'
    >(null);
    const hasSocialLogin = socialProviders.google || socialProviders.github;

    const form = useAppForm({
        defaults: {
            email: '',
            password: '',
            remember: false,
        },
        rememberKey: 'agency.auth.sign-in',
        dontRemember: ['password'],
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('login.store'), {
            resetOnSuccess: ['password'],
        });
    };

    const handleSocialLogin = (provider: 'google' | 'github') => {
        setLoadingProvider(provider);
        window.location.assign(route('social.login', { provider }));
    };

    return (
        <AgencyAuthLayout
            title="Welcome back"
            description="Sign in to manage websites, billing, and support for your agency workspace."
            eyebrow="Agency Portal"
            headline="Customer websites, billing, and support under one roof."
            supportingText="Access your active sites, review subscription activity, and stay close to support without leaving the agency workspace."
            bottomPrompt={
                canRegister ? (
                    <>
                        Need an account?{' '}
                        <Link
                            href={route('agency.get-started')}
                            className="font-medium text-foreground underline underline-offset-4"
                        >
                            Create one here
                        </Link>
                    </>
                ) : (
                    'Contact support if you need access.'
                )
            }
        >
            <div className="space-y-6">
                {status && (
                    <div className="rounded-2xl border border-[var(--success-border)] bg-[var(--success-bg)] px-4 py-3 text-sm font-medium text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]">
                        {status}
                    </div>
                )}

                <form
                    onSubmit={submit}
                    noValidate
                    className="space-y-5 inert:pointer-events-none inert:opacity-60"
                >
                    <Field
                        data-invalid={
                            form.invalid('email') ||
                            form.invalid('password') ||
                            undefined
                        }
                    >
                        <FieldLabel htmlFor="email">Email address</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autoFocus
                            autoComplete="email"
                            placeholder="you@example.com"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setField('email', event.target.value)
                            }
                            onBlur={() => form.touch('email')}
                            size="xl"
                        />
                        <FieldError>{form.error('email')}</FieldError>
                    </Field>

                    <Field
                        data-invalid={
                            form.invalid('password') ||
                            form.invalid('email') ||
                            undefined
                        }
                    >
                        <div className="flex items-center">
                            <FieldLabel htmlFor="password">Password</FieldLabel>
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="ml-auto text-sm font-medium text-muted-foreground hover:text-foreground"
                                >
                                    Forgot password?
                                </Link>
                            )}
                        </div>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autoComplete="current-password"
                            placeholder="Password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setField('password', event.target.value)
                            }
                            onBlur={() => form.touch('password')}
                            size="xl"
                        />
                        <FieldError>{form.error('password')}</FieldError>
                    </Field>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="remember"
                            checked={form.data.remember}
                            onCheckedChange={(checked) =>
                                form.setField('remember', checked === true)
                            }
                        />
                        <Label htmlFor="remember">Remember me</Label>
                    </div>

                    <Button
                        type="submit"
                        size="xl"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Sign in
                    </Button>
                </form>

                {hasSocialLogin && (
                    <>
                        <div className="flex items-center gap-3">
                            <div className="h-px flex-1 bg-border" />
                            <span className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                Or continue with
                            </span>
                            <div className="h-px flex-1 bg-border" />
                        </div>

                        <div className="grid gap-3">
                            {socialProviders.google && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="xl"
                                    className="justify-center gap-2"
                                    disabled={form.processing || loadingProvider !== null}
                                    onClick={() => handleSocialLogin('google')}
                                >
                                    {loadingProvider === 'google' ? (
                                        <Spinner />
                                    ) : (
                                        <span className="font-medium">Google</span>
                                    )}
                                </Button>
                            )}

                            {socialProviders.github && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="xl"
                                    className="justify-center gap-2"
                                    disabled={form.processing || loadingProvider !== null}
                                    onClick={() => handleSocialLogin('github')}
                                >
                                    {loadingProvider === 'github' ? (
                                        <Spinner />
                                    ) : (
                                        <span className="font-medium">GitHub</span>
                                    )}
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </div>
        </AgencyAuthLayout>
    );
}
