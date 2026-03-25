import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, ChevronRightIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AgencyAuthLayout from '../../../components/agency-auth-layout';
import AgencySocialAuthOptions from '../../../components/agency-social-auth-options';

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
    const form = useAppForm({
        defaults: {
            email: '',
            password: '',
            remember: false,
        },
        rememberKey: 'agency.auth.sign-in',
        dontRemember: ['password'],
    });
    const hasSocialLogin = socialProviders.google || socialProviders.github;
    const [loadingProvider, setLoadingProvider] = useState<
        null | 'google' | 'github'
    >(null);
    const [showEmailForm, setShowEmailForm] = useState(
        !hasSocialLogin || form.invalid('email') || form.invalid('password'),
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('login.store'), {
            onSuccess: () => {
                form.reset('password');
            },
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
                        <FieldGroup>
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

                            <Field
                                data-invalid={
                                    form.invalid('password') ||
                                    form.invalid('email') ||
                                    undefined
                                }
                            >
                                <div className="flex items-center gap-3">
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
                                    aria-invalid={form.invalid('password') || undefined}
                                    size="xl"
                                />
                                <FieldError>{form.error('password')}</FieldError>
                            </Field>
                        </FieldGroup>

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

                        <div className="flex flex-col gap-3">
                            <Button
                                type="submit"
                                size="xl"
                                className="w-full"
                                disabled={form.processing || loadingProvider !== null}
                            >
                                Sign in
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
            </div>
        </AgencyAuthLayout>
    );
}
