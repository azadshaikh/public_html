import { Link } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AgencyAuthLayout from '../../../components/agency-auth-layout';

type Props = {
    status?: string;
    canLogin: boolean;
    socialProviders: {
        google: boolean;
        github: boolean;
    };
};

export default function AgencyRegister({
    status,
    canLogin,
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
            password_confirmation: '',
            terms: '1',
        },
        rememberKey: 'agency.auth.register',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('agency.get-started.store'), {
            onSuccess: () => {
                form.reset('password', 'password_confirmation');
            },
        });
    };

    const handleSocialLogin = (provider: 'google' | 'github') => {
        setLoadingProvider(provider);
        window.location.assign(route('social.login', { provider }));
    };

    return (
        <AgencyAuthLayout
            title="Create your agency account"
            description="Start the guided onboarding flow and launch your first website from one streamlined workspace."
            eyebrow="Website Builder"
            headline="Launch your website in minutes."
            supportingText="Pick a domain, choose a plan, and move straight into provisioning with a registration flow tailored for the agency module."
            bottomPrompt={
                canLogin ? (
                    <>
                        Already have an account?{' '}
                        <Link
                            href={route('agency.sign-in')}
                            className="font-medium text-foreground underline underline-offset-4"
                        >
                            Sign in
                        </Link>
                    </>
                ) : (
                    'Registration is currently invite-based.'
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
                    <input type="hidden" name="terms" value={form.data.terms} />

                    <Field data-invalid={form.invalid('email') || undefined}>
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

                    <Field data-invalid={form.invalid('password') || undefined}>
                        <FieldLabel htmlFor="password">Password</FieldLabel>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autoComplete="new-password"
                            placeholder="Create a password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setField('password', event.target.value)
                            }
                            onBlur={() => form.touch('password')}
                            size="xl"
                        />
                        <FieldError>{form.error('password')}</FieldError>
                    </Field>

                    <Field
                        data-invalid={
                            form.invalid('password_confirmation') || undefined
                        }
                    >
                        <FieldLabel htmlFor="password_confirmation">
                            Confirm password
                        </FieldLabel>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autoComplete="new-password"
                            placeholder="Confirm your password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setField(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            onBlur={() =>
                                form.touch('password_confirmation')
                            }
                            size="xl"
                        />
                        <FieldError>
                            {form.error('password_confirmation')}
                        </FieldError>
                    </Field>

                    <Button
                        type="submit"
                        size="xl"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Create account
                    </Button>

                    <p className="text-center text-sm leading-6 text-muted-foreground">
                        By continuing, you agree to our{' '}
                        <a
                            href="/terms"
                            target="_blank"
                            rel="noreferrer"
                            className="font-medium text-foreground underline underline-offset-4"
                        >
                            Terms of Service
                        </a>{' '}
                        and{' '}
                        <a
                            href="/privacy"
                            target="_blank"
                            rel="noreferrer"
                            className="font-medium text-foreground underline underline-offset-4"
                        >
                            Privacy Policy
                        </a>
                        .
                    </p>
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
                                        <span className="font-medium">Continue with Google</span>
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
                                        <span className="font-medium">Continue with GitHub</span>
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
