import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, ChevronRightIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import { registerSocialLoginPendingReset } from '@/lib/social-login-pending-reset';
import AgencyAuthLayout from '../../../components/agency-auth-layout';
import AgencySocialAuthOptions from '../../../components/agency-social-auth-options';

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
    const form = useAppForm({
        defaults: {
            email: '',
            password: '',
            password_confirmation: '',
            terms: '1',
        },
        rememberKey: 'agency.auth.register',
    });
    const hasSocialLogin = socialProviders.google || socialProviders.github;
    const [loadingProvider, setLoadingProvider] = useState<
        null | 'google' | 'github'
    >(null);
    const [showEmailForm, setShowEmailForm] = useState(
        !hasSocialLogin ||
            form.invalid('email') ||
            form.invalid('password') ||
            form.invalid('password_confirmation'),
    );

    useEffect(() => {
        return registerSocialLoginPendingReset(window, () => {
            setLoadingProvider(null);
        });
    }, []);

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
            <div className="flex flex-col gap-6">
                {status && (
                    <div className="rounded-2xl border border-[var(--success-border)] bg-[var(--success-bg)] px-4 py-3 text-sm font-medium text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]">
                        {status}
                    </div>
                )}

                {!showEmailForm && hasSocialLogin ? (
                    <AgencySocialAuthOptions
                        socialProviders={socialProviders}
                        loadingProvider={loadingProvider}
                        disabled={form.processing || loadingProvider !== null}
                        emailButtonLabel="Continue with Email"
                        onProviderClick={handleSocialLogin}
                        onEmailClick={() => setShowEmailForm(true)}
                    />
                ) : (
                    <form
                        onSubmit={submit}
                        noValidate
                        className="flex flex-col gap-5 inert:pointer-events-none inert:opacity-60"
                    >
                        <input type="hidden" name="terms" value={form.data.terms} />

                        <FieldGroup>
                            <Field data-invalid={form.invalid('email') || undefined}>
                                <FieldLabel htmlFor="email">Email address</FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus={showEmailForm}
                                    autoComplete="email"
                                    placeholder="you@example.com"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setField('email', event.target.value)
                                    }
                                    onBlur={() => form.touch('email')}
                                    aria-invalid={form.invalid('email') || undefined}
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
                                    aria-invalid={form.invalid('password') || undefined}
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
                                    onBlur={() => form.touch('password_confirmation')}
                                    aria-invalid={
                                        form.invalid('password_confirmation') || undefined
                                    }
                                    size="xl"
                                />
                                <FieldError>
                                    {form.error('password_confirmation')}
                                </FieldError>
                            </Field>
                        </FieldGroup>

                        <div className="flex flex-col gap-3">
                            <Button
                                type="submit"
                                size="xl"
                                className="w-full"
                                disabled={form.processing || loadingProvider !== null}
                            >
                                Create account
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    <ChevronRightIcon data-icon="inline-end" />
                                )}
                            </Button>

                            {hasSocialLogin ? (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="xl"
                                    className="w-full"
                                    disabled={form.processing || loadingProvider !== null}
                                    onClick={() => setShowEmailForm(false)}
                                >
                                    <ArrowLeftIcon data-icon="inline-start" />
                                    Back
                                </Button>
                            ) : null}
                        </div>
                    </form>
                )}

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
            </div>
        </AgencyAuthLayout>
    );
}
