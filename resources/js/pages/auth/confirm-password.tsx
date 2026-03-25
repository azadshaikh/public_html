import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AuthLayout from '@/layouts/auth-layout';

export default function ConfirmPassword() {
    const form = useAppForm({
        defaults: {
            password: '',
        },
        dontRemember: ['password'],
        rememberKey: 'auth.confirm-password',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit('post', route('password.confirm.store'));
    };

    return (
        <AuthLayout
            title="Confirm your password"
            description="This is a secure area of the application. Please confirm your password before continuing."
        >
            <AppHead
                title="Confirm password"
                description="Re-enter your password before continuing to a protected area."
            />

            <form onSubmit={submit} noValidate>
                <div className="space-y-6">
                    <Field data-invalid={form.invalid('password') || undefined}>
                        <FieldLabel htmlFor="password">Password</FieldLabel>
                        <PasswordInput
                            id="password"
                            name="password"
                            size="xl"
                            placeholder="Password"
                            autoComplete="current-password"
                            autoFocus
                            value={form.data.password}
                            onChange={(e) =>
                                form.setField('password', e.target.value)
                            }
                            onBlur={() => form.touch('password')}
                            aria-invalid={form.invalid('password') || undefined}
                        />
                        <FieldError>{form.error('password')}</FieldError>
                    </Field>

                    <div className="flex items-center">
                        <Button
                            type="submit"
                            size="xl"
                            className="w-full"
                            disabled={form.processing}
                            data-test="confirm-password-button"
                        >
                            {form.processing && <Spinner />}
                            Confirm password
                        </Button>
                    </div>
                </div>
            </form>
        </AuthLayout>
    );
}
