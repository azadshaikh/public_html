import { Transition } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    InfoIcon,
    SaveIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import { useMemo, useRef } from 'react';
import type { FormEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Profile/ProfileController';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { FormValidationRules } from '@/lib/forms';
import { dashboard } from '@/routes';
import { profile as profileRoute } from '@/routes/app';
import { security as securityRoute } from '@/routes/app/profile';
import { password as passwordRoute } from '@/routes/app/profile/security';
import type { BreadcrumbItem } from '@/types';

type PasswordPageProps = {
    hasPassword: boolean;
};

type PasswordFormData = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

function RequiredLabel({
    htmlFor,
    children,
}: {
    htmlFor: string;
    children: string;
}) {
    return (
        <FieldLabel htmlFor={htmlFor}>
            {children}
            <span className="text-destructive"> *</span>
        </FieldLabel>
    );
}

export default function Password({ hasPassword }: PasswordPageProps) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const passwordTitle = hasPassword ? 'Manage Password' : 'Set Password';
    const passwordDescription = hasPassword
        ? 'Use a strong password and keep it updated to secure your account.'
        : 'Set a password to secure your account and enable password sign in.';
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Profile',
            href: profileRoute(),
        },
        {
            title: 'Security',
            href: securityRoute(),
        },
        {
            title: passwordTitle,
            href: passwordRoute(),
        },
    ];

    const validationRules = useMemo<FormValidationRules<PasswordFormData>>(
        () => ({
            current_password: hasPassword
                ? [formValidators.required('Current password')]
                : [],
            password: [
                formValidators.required('New password'),
                formValidators.minLength('New password', 8),
                (value, data) =>
                    value === data.password_confirmation
                        ? undefined
                        : 'Password confirmation does not match.',
            ],
            password_confirmation: [
                formValidators.required('Confirm password'),
                (value, data) =>
                    value === data.password
                        ? undefined
                        : 'Password confirmation does not match.',
            ],
        }),
        [hasPassword],
    );

    const form = useAppForm<PasswordFormData>({
        defaults: {
            current_password: '',
            password: '',
            password_confirmation: '',
        },
        rememberKey: 'account.password',
        dontRemember: ['current_password', 'password', 'password_confirmation'],
        dirtyGuard: {
            enabled: true,
        },
        rules: validationRules,
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(ProfileController.updatePassword(), {
            onError: (errors) => {
                if (errors.current_password) {
                    currentPasswordInput.current?.focus();

                    return;
                }

                if (errors.password) {
                    passwordInput.current?.focus();
                }
            },
            onSuccess: () => {
                form.reset();
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={passwordTitle}
            description={passwordDescription}
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={securityRoute()}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                {!hasPassword ? (
                    <Alert className="border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
                        <InfoIcon className="size-4 text-sky-600 dark:text-sky-300" />
                        <AlertDescription className="text-sky-800 dark:text-sky-100">
                            Your account currently uses social sign in only.
                            Set a password to enable direct password sign in.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <form
                    noValidate
                    className="flex flex-col gap-6"
                    onSubmit={handleSubmit}
                >
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} />

                    <Card className="py-6">
                        <CardContent className="px-6">
                            <FieldGroup>
                                {hasPassword ? (
                                    <Field
                                        data-invalid={
                                            form.invalid('current_password') ||
                                            undefined
                                        }
                                    >
                                        <RequiredLabel htmlFor="current_password">
                                            Current Password
                                        </RequiredLabel>
                                        <PasswordInput
                                            id="current_password"
                                            ref={currentPasswordInput}
                                            name="current_password"
                                            value={form.data.current_password}
                                            onChange={(event) =>
                                                form.setField(
                                                    'current_password',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('current_password')
                                            }
                                            aria-invalid={
                                                form.invalid('current_password') ||
                                                undefined
                                            }
                                            autoComplete="current-password"
                                            placeholder="Enter current password"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('current_password')}
                                        </FieldError>
                                    </Field>
                                ) : null}

                                <Field
                                    data-invalid={
                                        form.invalid('password') || undefined
                                    }
                                >
                                    <RequiredLabel htmlFor="password">
                                        New Password
                                    </RequiredLabel>
                                    <PasswordInput
                                        id="password"
                                        ref={passwordInput}
                                        name="password"
                                        value={form.data.password}
                                        onChange={(event) =>
                                            form.setField(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('password')}
                                        aria-invalid={
                                            form.invalid('password') || undefined
                                        }
                                        autoComplete="new-password"
                                        placeholder="Enter new password"
                                        size="comfortable"
                                    />
                                    <FieldError>{form.error('password')}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('password_confirmation') ||
                                        undefined
                                    }
                                >
                                    <RequiredLabel htmlFor="password_confirmation">
                                        Confirm Password
                                    </RequiredLabel>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        value={form.data.password_confirmation}
                                        onChange={(event) =>
                                            form.setField(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch(
                                                'password_confirmation',
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('password_confirmation') ||
                                            undefined
                                        }
                                        autoComplete="new-password"
                                        placeholder="Confirm your password"
                                        size="comfortable"
                                    />
                                    <FieldError>
                                        {form.error('password_confirmation')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Alert className="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                        <TriangleAlertIcon className="size-4 text-amber-600 dark:text-amber-300" />
                        <AlertDescription className="font-medium text-amber-800 dark:text-amber-100">
                            {hasPassword
                                ? 'All other active sessions will end after you change the password. Your current session will remain signed in.'
                                : 'After you set a password, your current session will stay signed in and future password sign ins will be enabled.'}
                        </AlertDescription>
                    </Alert>

                    <div className="flex flex-col gap-3">
                        <Button
                            type="submit"
                            size="lg"
                            className="h-11 w-full rounded-xl text-base font-semibold"
                            disabled={form.processing}
                            data-test="update-password-button"
                        >
                            <SaveIcon data-icon="inline-start" />
                            {form.processing
                                ? hasPassword
                                    ? 'Updating...'
                                    : 'Setting...'
                                : hasPassword
                                  ? 'Update Password'
                                  : 'Set Password'}
                        </Button>

                        <div className="flex min-h-5 items-center justify-center">
                            {form.isDirty && !form.processing ? (
                                <p className="text-center text-sm text-muted-foreground">
                                    You have unsaved changes.
                                </p>
                            ) : (
                                <Transition
                                    show={form.recentlySuccessful}
                                    enter="transition ease-out duration-200"
                                    enterFrom="opacity-0 translate-y-1"
                                    enterTo="opacity-100 translate-y-0"
                                    leave="transition ease-in duration-150"
                                    leaveFrom="opacity-100 translate-y-0"
                                    leaveTo="opacity-0 translate-y-1"
                                >
                                    <p className="text-center text-sm text-muted-foreground">
                                        Password updated.
                                    </p>
                                </Transition>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
