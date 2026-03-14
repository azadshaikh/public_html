import { Form } from '@inertiajs/react';
import { useState } from 'react';
import AppHead from '@/components/app-head';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';

type Props = {
    status?: string;
    canLogin: boolean;
    socialProviders: {
        google: boolean;
        github: boolean;
    };
};

export default function Register({ status, canLogin, socialProviders }: Props) {
    const [loadingProvider, setLoadingProvider] = useState<
        null | 'google' | 'github'
    >(null);

    const hasSocialLogin = socialProviders.google || socialProviders.github;

    const handleSocialLogin = (provider: 'google' | 'github') => {
        setLoadingProvider(provider);
        window.location.assign(route('social.login', { provider }));
    };

    return (
        <AuthLayout
            title="Create an account"
            description="Enter your email and password to get started."
        >
            <AppHead
                title="Register"
                description="Create a new account to start using the application."
            />

            {status && (
                <div className="rounded-md border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:text-emerald-400">
                    {status}
                </div>
            )}

            <Form
                action={route('register.store')}
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6 inert:pointer-events-none inert:opacity-60"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-start gap-3 rounded-lg border border-border/60 bg-muted/20 p-4">
                                    <input
                                        id="terms"
                                        name="terms"
                                        type="checkbox"
                                        value="1"
                                        required
                                        tabIndex={4}
                                        className="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                    />
                                    <Label
                                        htmlFor="terms"
                                        className="text-sm leading-6 text-muted-foreground"
                                    >
                                        I agree to the{' '}
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
                                    </Label>
                                </div>
                                <InputError message={errors.terms} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

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
                                            className="w-full justify-center gap-2"
                                            disabled={
                                                processing ||
                                                loadingProvider !== null
                                            }
                                            onClick={() =>
                                                handleSocialLogin('google')
                                            }
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
                                            Continue with Google
                                        </Button>
                                    )}

                                    {socialProviders.github && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full justify-center gap-2"
                                            disabled={
                                                processing ||
                                                loadingProvider !== null
                                            }
                                            onClick={() =>
                                                handleSocialLogin('github')
                                            }
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
                                            Continue with GitHub
                                        </Button>
                                    )}
                                </div>
                            </>
                        )}

                        {canLogin && (
                            <div className="text-center text-sm text-muted-foreground">
                                Already have an account?{' '}
                                <TextLink href={route('login')} tabIndex={6}>
                                    Log in
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
