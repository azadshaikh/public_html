import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AuthLayout from '@/layouts/auth-layout';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    const form = useAppForm({
        defaults: {
            token,
            email,
            password: '',
            password_confirmation: '',
        },
        dontRemember: ['password', 'password_confirmation'],
        rememberKey: 'auth.reset-password',
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.submit('post', route('password.store'));
    };

    return (
        <AuthLayout
            title="Reset password"
            description="Choose a new password to finish resetting your account."
        >
            <AppHead
                title="Reset password"
                description="Choose a new password for your account."
            />

            <form onSubmit={submit} noValidate className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <Field data-invalid={form.invalid('email') || undefined}>
                        <FieldLabel htmlFor="email">Email address</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setField('email', e.target.value)
                            }
                            onBlur={() => form.touch('email')}
                            aria-invalid={form.invalid('email') || undefined}
                            readOnly
                            size="xl"
                        />
                        <FieldError>{form.error('email')}</FieldError>
                    </Field>

                    <Field data-invalid={form.invalid('password') || undefined}>
                        <FieldLabel htmlFor="password">New password</FieldLabel>
                        <PasswordInput
                            id="password"
                            name="password"
                            autoComplete="new-password"
                            autoFocus
                            placeholder="New password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setField('password', e.target.value)
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
                            autoComplete="new-password"
                            placeholder="Confirm password"
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setField(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                            onBlur={() => form.touch('password_confirmation')}
                            aria-invalid={
                                form.invalid('password_confirmation') ||
                                undefined
                            }
                            size="xl"
                        />
                        <FieldError>
                            {form.error('password_confirmation')}
                        </FieldError>
                    </Field>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                        size="xl"
                        data-test="reset-password-button"
                    >
                        {form.processing && <Spinner />}
                        Save new password
                    </Button>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    <TextLink href={route('login')}>Back to log in</TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
