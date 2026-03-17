import type { FormEvent } from 'react';
import AppHead from '@/components/app-head';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AuthLayout from '@/layouts/auth-layout';

type Props = {
    user: {
        first_name: string | null;
        last_name: string | null;
        email: string | null;
    };
};

export default function ProfileComplete({ user }: Props) {
    const form = useAppForm({
        defaults: {
            first_name: user.first_name ?? '',
            last_name: user.last_name ?? '',
        },
        rememberKey: 'auth.profile-complete',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('profile.complete.store'));
    };

    return (
        <AuthLayout
            title="Complete your profile"
            description="Add your name to finish setting up your account."
        >
            <AppHead
                title="Complete profile"
                description="Finish setting up your account by adding your name."
            />

            <div className="space-y-6">
                <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
                    <p>
                        Signed in as{' '}
                        <span className="font-medium text-foreground">
                            {user.email ?? 'your account'}
                        </span>
                        .
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    noValidate
                    className="flex flex-col gap-6"
                >
                    <div className="grid gap-6">
                        <Field
                            data-invalid={
                                form.invalid('first_name') || undefined
                            }
                        >
                            <FieldLabel htmlFor="first_name">
                                First name
                            </FieldLabel>
                            <Input
                                id="first_name"
                                name="first_name"
                                value={form.data.first_name}
                                onChange={(e) =>
                                    form.setField('first_name', e.target.value)
                                }
                                onBlur={() => form.touch('first_name')}
                                aria-invalid={
                                    form.invalid('first_name') || undefined
                                }
                                autoComplete="given-name"
                                autoFocus
                                placeholder="First name"
                                size="xl"
                            />
                            <FieldError>{form.error('first_name')}</FieldError>
                        </Field>

                        <Field
                            data-invalid={
                                form.invalid('last_name') || undefined
                            }
                        >
                            <FieldLabel htmlFor="last_name">
                                Last name
                            </FieldLabel>
                            <Input
                                id="last_name"
                                name="last_name"
                                value={form.data.last_name}
                                onChange={(e) =>
                                    form.setField('last_name', e.target.value)
                                }
                                onBlur={() => form.touch('last_name')}
                                aria-invalid={
                                    form.invalid('last_name') || undefined
                                }
                                autoComplete="family-name"
                                placeholder="Last name"
                                size="xl"
                            />
                            <FieldError>{form.error('last_name')}</FieldError>
                        </Field>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={form.processing}
                            size="xl"
                        >
                            {form.processing && <Spinner />}
                            Continue to dashboard
                        </Button>
                    </div>
                </form>
            </div>
        </AuthLayout>
    );
}
