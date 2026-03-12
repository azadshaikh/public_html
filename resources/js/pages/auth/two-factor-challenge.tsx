import { Form } from '@inertiajs/react';
import AppHead from '@/components/app-head';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes/index';
import { store } from '@/routes/two-factor/challenge';

type Props = {
    email?: string | null;
};

export default function TwoFactorChallenge({ email }: Props) {
    return (
        <AuthLayout
            title="Two-factor authentication"
            description="Enter the code from your authenticator app or one of your recovery codes."
        >
            <AppHead
                title="Two-factor authentication"
                description="Confirm your sign-in with an authentication code or recovery code."
            />

            <div className="space-y-6">
                {email && (
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-center text-sm text-muted-foreground">
                        Continuing sign-in for{' '}
                        <span className="font-medium text-foreground">
                            {email}
                        </span>
                    </div>
                )}

                <Form
                    {...store.form()}
                    className="space-y-6"
                    resetOnError
                    resetOnSuccess={['code']}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <label
                                    htmlFor="code"
                                    className="text-sm font-medium"
                                >
                                    Authentication code or recovery code
                                </label>
                                <Input
                                    id="code"
                                    name="code"
                                    autoFocus
                                    autoComplete="one-time-code"
                                    placeholder="Enter your code"
                                />
                                <p className="text-sm text-muted-foreground">
                                    You can paste a 6-digit code from your
                                    authenticator app or enter one of your saved
                                    recovery codes.
                                </p>
                                <InputError message={errors.code} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Continue
                            </Button>

                            <div className="text-center text-sm text-muted-foreground">
                                Need to start over?{' '}
                                <TextLink href={login()}>
                                    Back to log in
                                </TextLink>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}
