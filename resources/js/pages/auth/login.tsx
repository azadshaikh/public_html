import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AuthLayout from '@/layouts/auth-layout';
import { registerSocialLoginPendingReset } from '@/lib/social-login-pending-reset';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    socialProviders: {
        google: boolean;
        github: boolean;
    };
};

export default function Login({
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
        rememberKey: 'auth.login',
        dontRemember: ['password'],
    });

    useEffect(() => {
        return registerSocialLoginPendingReset(window, () => {
            setLoadingProvider(null);
        });
    }, []);

    const handleSocialLogin = (provider: 'google' | 'github') => {
        setLoadingProvider(provider);
        window.location.assign(route('social.login', { provider }));
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit('post', route('login.store'));
    };

    return (
        <AuthLayout
            title="Welcome Back"
            description="Enter your credentials to access your account"
        >
            <AppHead
                title="Log in"
                description="Access your account using your email address and password."
            />

            {status && (
                <div className="rounded-md border border-[var(--success-border)] bg-[var(--success-bg)] px-4 py-3 text-center text-sm font-medium text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]">
                    {status}
                </div>
            )}

            <form
                onSubmit={submit}
                noValidate
                className="flex flex-col gap-6 inert:pointer-events-none inert:opacity-60"
            >
                <div className="grid gap-6">
                    <Field
                        data-invalid={
                            form.invalid('email') ||
                            form.invalid('password') ||
                            undefined
                        }
                    >
                        <FieldLabel htmlFor="email">Email Address</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            size="xl"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            placeholder="email@example.com"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setField('email', e.target.value)
                            }
                            onBlur={() => form.touch('email')}
                            aria-invalid={
                                form.invalid('email') ||
                                form.invalid('password') ||
                                undefined
                            }
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
                                <TextLink
                                    href={route('password.request')}
                                    className="ml-auto text-sm"
                                    tabIndex={5}
                                >
                                    Forgot Password?
                                </TextLink>
                            )}
                        </div>
                        <PasswordInput
                            id="password"
                            name="password"
                            size="xl"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            placeholder="Password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setField('password', e.target.value)
                            }
                            onBlur={() => form.touch('password')}
                            aria-invalid={
                                form.invalid('password') ||
                                form.invalid('email') ||
                                undefined
                            }
                        />
                        <FieldError>{form.error('password')}</FieldError>
                    </Field>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            checked={form.data.remember}
                            onCheckedChange={(checked) =>
                                form.setField('remember', checked === true)
                            }
                            tabIndex={3}
                        />
                        <Label htmlFor="remember">Remember me</Label>
                    </div>

                    <Button
                        type="submit"
                        size="xl"
                        className="mt-2 w-full"
                        tabIndex={4}
                        disabled={form.processing}
                        data-test="login-button"
                    >
                        {form.processing && <Spinner />}
                        Sign In
                    </Button>
                </div>

                {hasSocialLogin && (
                    <>
                        <div className="flex items-center gap-3">
                            <div className="h-px flex-1 bg-border" />
                            <span className="text-xs text-muted-foreground">
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
                                    className="w-full justify-center gap-2"
                                    disabled={
                                        form.processing ||
                                        loadingProvider !== null
                                    }
                                    onClick={() => handleSocialLogin('google')}
                                >
                                    {loadingProvider === 'google' ? (
                                        <Spinner />
                                    ) : (
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="18"
                                            height="18"
                                            viewBox="0 0 48 48"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fill="#FFC107"
                                                d="M43.611 20.083H42V20H24v8h11.303C33.655 32.657 29.195 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.963 3.037l5.657-5.657C34.046 6.053 29.278 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"
                                            />
                                            <path
                                                fill="#FF3D00"
                                                d="M6.306 14.691 12.88 19.51A11.99 11.99 0 0 1 24 12c3.059 0 5.842 1.154 7.963 3.037l5.657-5.657C34.046 6.053 29.278 4 24 4c-7.682 0-14.353 4.337-17.694 10.691z"
                                            />
                                            <path
                                                fill="#4CAF50"
                                                d="M24 44c5.176 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.159 35.09 26.715 36 24 36c-5.175 0-9.628-3.327-11.286-7.946l-6.523 5.025C9.499 39.556 16.227 44 24 44z"
                                            />
                                            <path
                                                fill="#1976D2"
                                                d="M43.611 20.083H42V20H24v8h11.303a12.05 12.05 0 0 1-4.084 5.57l.003-.002 6.19 5.238C36.971 39.204 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"
                                            />
                                        </svg>
                                    )}
                                    Google
                                </Button>
                            )}

                            {socialProviders.github && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="xl"
                                    className="w-full justify-center gap-2"
                                    disabled={
                                        form.processing ||
                                        loadingProvider !== null
                                    }
                                    onClick={() => handleSocialLogin('github')}
                                >
                                    {loadingProvider === 'github' ? (
                                        <Spinner />
                                    ) : (
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="18"
                                            height="18"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path d="M12 2C6.477 2 2 6.596 2 12.267c0 4.537 2.865 8.387 6.839 9.746.5.096.682-.222.682-.493 0-.243-.009-.888-.014-1.744-2.782.617-3.369-1.376-3.369-1.376-.455-1.188-1.11-1.505-1.11-1.505-.909-.637.069-.624.069-.624 1.004.072 1.532 1.06 1.532 1.06.892 1.567 2.341 1.115 2.91.853.091-.666.349-1.115.635-1.372-2.22-.26-4.555-1.14-4.555-5.074 0-1.121.389-2.037 1.029-2.754-.103-.261-.446-1.312.098-2.736 0 0 .84-.274 2.75 1.052A9.313 9.313 0 0 1 12 6.844c.85.004 1.705.118 2.504.347 1.909-1.326 2.747-1.052 2.747-1.052.546 1.424.203 2.475.1 2.736.64.717 1.027 1.633 1.027 2.754 0 3.944-2.339 4.811-4.566 5.066.359.319.678.948.678 1.911 0 1.379-.012 2.491-.012 2.829 0 .274.18.594.688.492C19.137 20.65 22 16.802 22 12.267 22 6.596 17.523 2 12 2z" />
                                        </svg>
                                    )}
                                    GitHub
                                </Button>
                            )}
                        </div>
                    </>
                )}

                {canRegister && (
                    <div className="text-center text-sm text-muted-foreground">
                        Don't have an account?{' '}
                        <TextLink href={route('register')} tabIndex={5}>
                            Create Account
                        </TextLink>
                    </div>
                )}
            </form>
        </AuthLayout>
    );
}
