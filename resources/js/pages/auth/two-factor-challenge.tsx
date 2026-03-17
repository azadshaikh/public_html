import { ShieldIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAppForm } from '@/hooks/use-app-form';
import AuthLayout from '@/layouts/auth-layout';

type Props = {
    email?: string | null;
};

type TwoFactorChallengeFormData = {
    code: string;
    recovery_code: string;
};

export default function TwoFactorChallenge({ email }: Props) {
    const [method, setMethod] = useState<'authenticator' | 'recovery'>(
        'authenticator',
    );
    const form = useAppForm<TwoFactorChallengeFormData>({
        defaults: {
            code: '',
            recovery_code: '',
        },
        rememberKey: 'auth.two-factor-challenge',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            code: method === 'authenticator' ? data.code : data.recovery_code,
        }));

        form.submit('post', route('two-factor.challenge.store'), {
            onFinish: () => {
                form.transform((data) => data);
            },
        });
    };

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

                <form noValidate className="space-y-6" onSubmit={handleSubmit}>
                    <Tabs
                        value={method}
                        onValueChange={(value) => {
                            if (
                                value === 'authenticator' ||
                                value === 'recovery'
                            ) {
                                setMethod(value);
                                form.clearErrors('code');
                            }
                        }}
                    >
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="authenticator">
                                Authenticator code
                            </TabsTrigger>
                            <TabsTrigger value="recovery">
                                Recovery code
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    {method === 'authenticator' ? (
                        <Field data-invalid={form.invalid('code') || undefined}>
                            <FieldLabel htmlFor="code">
                                Authentication code
                            </FieldLabel>
                            <InputOTP
                                id="code"
                                name="code"
                                size="xl"
                                value={form.data.code}
                                onChange={(value) =>
                                    form.setData(
                                        'code',
                                        value.replace(/\D/g, ''),
                                    )
                                }
                                aria-invalid={Boolean(form.errors.code)}
                                autoFocus
                                autoComplete="one-time-code"
                                inputMode="numeric"
                                maxLength={6}
                                containerClassName="w-full"
                            >
                                <InputOTPGroup className="w-full">
                                    {Array.from({ length: 6 }).map(
                                        (_, index) => (
                                            <InputOTPSlot
                                                key={index}
                                                index={index}
                                                className="flex-1"
                                            />
                                        ),
                                    )}
                                </InputOTPGroup>
                            </InputOTP>
                            <p className="text-sm text-muted-foreground">
                                Enter the current 6-digit code from your
                                authenticator app.
                            </p>
                            <FieldError>{form.error('code')}</FieldError>
                        </Field>
                    ) : (
                        <Field data-invalid={form.invalid('code') || undefined}>
                            <FieldLabel htmlFor="recovery_code">
                                Recovery code
                            </FieldLabel>
                            <Input
                                id="recovery_code"
                                name="recovery_code"
                                value={form.data.recovery_code}
                                onChange={(event) =>
                                    form.setData(
                                        'recovery_code',
                                        event.target.value,
                                    )
                                }
                                autoFocus
                                autoCapitalize="characters"
                                autoComplete="off"
                                placeholder="Enter your recovery code"
                                aria-invalid={Boolean(form.errors.code)}
                                size="xl"
                            />
                            <p className="text-sm text-muted-foreground">
                                Use one of the backup recovery codes you saved
                                when two-factor authentication was enabled.
                            </p>
                            <FieldError>{form.error('code')}</FieldError>
                        </Field>
                    )}

                    <Button
                        type="submit"
                        size="xl"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <Spinner className="mr-2 h-4 w-4" />
                        ) : (
                            <ShieldIcon className="mr-2 h-4 w-4" />
                        )}
                        Continue
                    </Button>

                    <div className="text-center text-sm text-muted-foreground">
                        Need to start over?{' '}
                        <TextLink href={route('login')}>
                            Back to log in
                        </TextLink>
                    </div>
                </form>
            </div>
        </AuthLayout>
    );
}
