import { useForm } from '@inertiajs/react';
import { ShieldIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
    const form = useForm<TwoFactorChallengeFormData>({
        code: '',
        recovery_code: '',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            code: method === 'authenticator' ? data.code : data.recovery_code,
        }));

        form.post(route('two-factor.challenge.store'), {
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
                        <div className="grid gap-2">
                            <label
                                htmlFor="code"
                                className="text-sm font-medium"
                            >
                                Authentication code
                            </label>
                            <InputOTP
                                id="code"
                                name="code"
                                size="comfortable"
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
                            <InputError message={form.errors.code} />
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <label
                                htmlFor="recovery_code"
                                className="text-sm font-medium"
                            >
                                Recovery code
                            </label>
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
                                size="comfortable"
                            />
                            <p className="text-sm text-muted-foreground">
                                Use one of the backup recovery codes you saved
                                when two-factor authentication was enabled.
                            </p>
                            <InputError message={form.errors.code} />
                        </div>
                    )}

                    <Button
                        type="submit"
                        size="comfortable"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? <Spinner /> : <ShieldIcon />}
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
