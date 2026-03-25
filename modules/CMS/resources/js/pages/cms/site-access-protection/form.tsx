import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { useAppForm } from '@/hooks/use-app-form';
import AuthCardLayout from '@/layouts/auth/auth-card-layout';

type SiteAccessProtectionPageProps = {
    message: string;
};

export default function SiteAccessProtectionForm({
    message,
}: SiteAccessProtectionPageProps) {
    const form = useAppForm({
        defaults: {
            password: '',
        },
        rememberKey: 'cms.site-access-protection',
        dontRemember: ['password'],
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('site.access.protection.verify'), {
            setDefaultsOnSuccess: true,
        });
    };

    return (
        <AuthCardLayout
            title="Site Access Protection"
            description={message}
        >
            <AppHead
                title="Site Access Protection"
                description={message}
            />

            <form
                onSubmit={handleSubmit}
                noValidate
                className="flex flex-col gap-6"
            >
                <Field data-invalid={form.invalid('password') || undefined}>
                    <FieldLabel htmlFor="password">
                        Enter access password
                    </FieldLabel>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autoFocus
                        autoComplete="current-password"
                        placeholder="Enter password to continue"
                        value={form.data.password}
                        onChange={(event) =>
                            form.setField('password', event.target.value)
                        }
                        onBlur={() => form.touch('password')}
                        aria-invalid={form.invalid('password') || undefined}
                    />
                    <FieldError>{form.error('password')}</FieldError>
                </Field>

                <Button type="submit" size="xl" disabled={form.processing}>
                    {form.processing ? 'Verifying...' : 'Verify Password'}
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    This site is protected. Enter the password to continue.
                </p>
            </form>
        </AuthCardLayout>
    );
}
