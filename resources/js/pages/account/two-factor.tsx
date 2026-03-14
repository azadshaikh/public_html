import { Link, router } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    CheckIcon,
    CopyIcon,
    KeyRoundIcon,
    PrinterIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    ShieldOffIcon,
    SmartphoneIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { showAppToast } from '@/components/forms/form-success-toast';
import { suppressNextFlashToast } from '@/hooks/use-flash-toast';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { useAppForm } from '@/hooks/use-app-form';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { FormValidationRules } from '@/lib/forms';
import type { BreadcrumbItem } from '@/types';

type TwoFactorPageProps = {
    twoFactorEnabled: boolean;
    twoFactorPending: boolean;
    twoFactorSetupKey: string | null;
    twoFactorQrCodeDataUri: string | null;
    twoFactorRecoveryCodes: string[];
};

type ConfirmCodeFormData = {
    code: string;
};

type PasswordActionFormData = {
    current_password: string;
};

type PasswordAction = 'disable' | 'regenerate' | null;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
    },
    {
        title: 'Profile',
        href: route('app.profile'),
    },
    {
        title: 'Security',
        href: route('app.profile.security'),
    },
    {
        title: 'Two-Factor Authentication (2FA)',
        href: route('app.profile.security.two-factor'),
    },
];

function StatusPill({
    tone,
    children,
}: {
    tone: 'success' | 'warning' | 'muted';
    children: string;
}) {
    const variants = {
        success: 'success',
        warning: 'warning',
        muted: 'secondary',
    } as const;

    return <Badge variant={variants[tone]}>{children}</Badge>;
}

function SectionCard({
    title,
    description,
    status,
    children,
}: {
    title: string;
    description: string;
    status?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <Card className="py-6">
            <CardContent className="px-6">
                <div className="flex flex-col gap-6">
                    <div className="relative">
                        <div className="min-w-0 space-y-2 pr-28">
                            <h2 className="flex items-center gap-2 text-[1.05rem] font-semibold text-foreground">
                                <ShieldCheckIcon className="size-4.5 shrink-0" />
                                <span>{title}</span>
                            </h2>
                            <p className="text-sm leading-6 text-muted-foreground">
                                {description}
                            </p>
                        </div>

                        {status ? (
                            <div className="absolute top-0 right-0">
                                {status}
                            </div>
                        ) : null}
                    </div>

                    {children}
                </div>
            </CardContent>
        </Card>
    );
}

function RecoveryCodesDialog({
    open,
    onOpenChange,
    codes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    codes: string[];
}) {
    const [copiedText, copy] = useClipboard();
    const copiedAllCodes = copiedText === codes.join('\n');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Save your backup codes</DialogTitle>
                    <DialogDescription>
                        These recovery codes are shown only once. Store them in
                        a secure place before closing this dialog.
                    </DialogDescription>
                </DialogHeader>

                <Alert className="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                    <TriangleAlertIcon className="size-4 text-amber-600 dark:text-amber-300" />
                    <AlertTitle className="text-amber-900 dark:text-amber-100">
                        Save these now
                    </AlertTitle>
                    <AlertDescription className="text-amber-800 dark:text-amber-100">
                        You won&apos;t be able to view these exact codes again
                        after this dialog is closed.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-3 rounded-xl border bg-muted/30 p-4 font-mono text-sm sm:grid-cols-2">
                    {codes.map((code) => (
                        <div
                            key={code}
                            className="rounded-md bg-background px-4 py-3"
                        >
                            {code}
                        </div>
                    ))}
                </div>

                <DialogFooter className="sm:justify-between">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void copy(codes.join('\n'))}
                        >
                            <CopyIcon data-icon="inline-start" />
                            {copiedAllCodes ? 'Copied' : 'Copy'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <PrinterIcon data-icon="inline-start" />
                            Print
                        </Button>
                    </div>

                    <Button type="button" onClick={() => onOpenChange(false)}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function PasswordActionDialog({
    open,
    onOpenChange,
    action,
    form,
    onSubmit,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    action: PasswordAction;
    form: ReturnType<typeof useAppForm<PasswordActionFormData>>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
    const config =
        action === 'regenerate'
            ? {
                  title: 'Regenerate recovery codes',
                  description:
                      'Enter your current password to generate a fresh set of backup codes.',
                  buttonLabel: 'Regenerate codes',
              }
            : {
                  title: 'Disable two-factor authentication',
                  description:
                      'Enter your current password to remove the extra sign-in verification step.',
                  buttonLabel: 'Disable 2FA',
              };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{config.title}</DialogTitle>
                    <DialogDescription>{config.description}</DialogDescription>
                </DialogHeader>

                <form noValidate className="space-y-4" onSubmit={onSubmit}>
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Field
                        data-invalid={
                            form.invalid('current_password') || undefined
                        }
                    >
                        <FieldLabel htmlFor="current_password">
                            Current Password
                        </FieldLabel>
                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            value={form.data.current_password}
                            onChange={(event) =>
                                form.setField(
                                    'current_password',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('current_password')}
                            aria-invalid={
                                form.invalid('current_password') || undefined
                            }
                            autoComplete="current-password"
                            placeholder="Enter current password"
                            size="comfortable"
                            autoFocus
                        />
                        <FieldError>
                            {form.error('current_password')}
                        </FieldError>
                    </Field>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={
                                action === 'disable' ? 'destructive' : 'default'
                            }
                            disabled={form.processing}
                        >
                            {config.buttonLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function TwoFactor({
    twoFactorEnabled,
    twoFactorPending,
    twoFactorSetupKey,
    twoFactorQrCodeDataUri,
    twoFactorRecoveryCodes,
}: TwoFactorPageProps) {
    const [startingSetup, setStartingSetup] = useState(false);
    const [passwordAction, setPasswordAction] = useState<PasswordAction>(null);
    const [dismissedRecoveryCodesKey, setDismissedRecoveryCodesKey] = useState<
        string | null
    >(null);
    const [copiedText, copy] = useClipboard();
    const recoveryCodesKey = useMemo(
        () => twoFactorRecoveryCodes.join('|'),
        [twoFactorRecoveryCodes],
    );
    const recoveryDialogOpen =
        twoFactorRecoveryCodes.length > 0 &&
        dismissedRecoveryCodesKey !== recoveryCodesKey;

    const confirmRules = useMemo<FormValidationRules<ConfirmCodeFormData>>(
        () => ({
            code: [
                formValidators.required('Authentication code'),
                (value) =>
                    /^\d{6}$/.test(value.trim())
                        ? undefined
                        : 'Authentication code must be 6 digits.',
            ],
        }),
        [],
    );

    const confirmForm = useAppForm<ConfirmCodeFormData>({
        defaults: {
            code: '',
        },
        rememberKey: 'account.two-factor.confirm',
        dontRemember: ['code'],
        rules: confirmRules,
    });

    const passwordActionForm = useAppForm<PasswordActionFormData>({
        defaults: {
            current_password: '',
        },
        rememberKey: 'account.two-factor.password-action',
        dontRemember: ['current_password'],
        rules: {
            current_password: [formValidators.required('Current password')],
        },
    });

    const copiedSetupKey = copiedText === twoFactorSetupKey;
    const status = twoFactorEnabled ? (
        <StatusPill tone="success">Enabled</StatusPill>
    ) : twoFactorPending ? (
        <StatusPill tone="warning">Setup Pending</StatusPill>
    ) : (
        <StatusPill tone="muted">Not Enabled</StatusPill>
    );

    const handleStartSetup = () => {
        router.post(route('app.profile.two-factor.store'), undefined, {
            preserveScroll: true,
            onStart: () => setStartingSetup(true),
            onSuccess: () => {
                suppressNextFlashToast();
                showAppToast({
                    title: 'Setup started',
                    description:
                        'Scan the QR code and confirm with the code from your authenticator app.',
                });
            },
            onFinish: () => setStartingSetup(false),
        });
    };

    const handleConfirmSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        confirmForm.submit('post', route('app.profile.two-factor.confirm'), {
            successToast: {
                title: 'Two-factor enabled',
                description:
                    'Your account now requires an authenticator code when signing in.',
            },
            onSuccess: () => {
                confirmForm.reset();
            },
        });
    };

    const handlePasswordActionSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (passwordAction === null) {
            return;
        }

        passwordActionForm.submit(
            passwordAction === 'disable' ? 'delete' : 'post',
            passwordAction === 'disable'
                ? route('app.profile.two-factor.destroy')
                : route('app.profile.two-factor.recovery-codes.regenerate'),
            {
                successToast:
                    passwordAction === 'disable'
                        ? {
                              title: 'Two-factor disabled',
                              description:
                                  'Your account no longer requires an authenticator code.',
                          }
                        : {
                              title: 'Recovery codes regenerated',
                              description:
                                  'A fresh set of backup codes is ready to save.',
                          },
                onSuccess: () => {
                    passwordActionForm.reset();
                    setPasswordAction(null);
                },
                onError: () => {
                    if (passwordAction !== null) {
                        setPasswordAction(passwordAction);
                    }
                },
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Two-Factor Authentication (2FA)"
            description="Set up and manage your authenticator app and recovery codes."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.profile.security')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                {!twoFactorEnabled && !twoFactorPending ? (
                    <SectionCard
                        title="Protection status"
                        description="Two-factor authentication is currently off. Turn it on to protect your account with a second sign-in check."
                        status={status}
                    >
                        <div>
                            <Button
                                type="button"
                                size="lg"
                                className="h-11 w-full rounded-xl text-base font-semibold"
                                onClick={handleStartSetup}
                                disabled={startingSetup}
                            >
                                <ShieldCheckIcon data-icon="inline-start" />
                                {startingSetup
                                    ? 'Starting setup...'
                                    : 'Enable Two-Factor Authentication'}
                            </Button>
                        </div>

                        <div className="grid gap-4 md:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
                            <div className="rounded-xl border bg-muted/15 p-5">
                                <div className="flex items-start gap-3">
                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-full bg-background text-foreground ring-1 ring-border">
                                        <ShieldCheckIcon className="size-5" />
                                    </div>
                                    <div className="space-y-3">
                                        <div>
                                            <h3 className="font-semibold text-foreground">
                                                Why enable it
                                            </h3>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Even if someone gets your
                                                password, they still can&apos;t
                                                sign in without the code from
                                                your authenticator app.
                                            </p>
                                        </div>

                                        <ul className="space-y-2 text-sm text-foreground">
                                            <li className="flex items-start gap-2">
                                                <CheckIcon className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    Extra protection for
                                                    password-based sign in.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <CheckIcon className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    Works with 1Password, Google
                                                    Authenticator, and Authy.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <CheckIcon className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                <span>
                                                    Setup usually takes less
                                                    than a minute.
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-xl border bg-background p-5">
                                <div className="space-y-4">
                                    <div>
                                        <h3 className="font-semibold text-foreground">
                                            What happens next
                                        </h3>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            Start setup and we&apos;ll walk you
                                            through two quick steps.
                                        </p>
                                    </div>

                                    <ol className="space-y-3 text-sm">
                                        <li className="flex gap-3">
                                            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted font-semibold text-foreground">
                                                1
                                            </span>
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    Add this account to your
                                                    authenticator app
                                                </p>
                                                <p className="text-muted-foreground">
                                                    Scan a QR code or paste a
                                                    setup key.
                                                </p>
                                            </div>
                                        </li>
                                        <li className="flex gap-3">
                                            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted font-semibold text-foreground">
                                                2
                                            </span>
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    Confirm with the 6-digit
                                                    code
                                                </p>
                                                <p className="text-muted-foreground">
                                                    We&apos;ll then generate
                                                    backup recovery codes for
                                                    you.
                                                </p>
                                            </div>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </SectionCard>
                ) : null}

                {twoFactorPending ? (
                    <SectionCard
                        title="Finish setup"
                        description="Add this account to your authenticator app, then enter the current 6-digit code to turn protection on."
                        status={status}
                    >
                        <div className="rounded-xl border bg-muted/15 p-4">
                            <div className="flex items-start gap-3">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-background text-foreground ring-1 ring-border">
                                    <SmartphoneIcon className="size-4.5" />
                                </div>
                                <div>
                                    <p className="font-medium text-foreground">
                                        Open your authenticator app
                                    </p>
                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Scan the QR code to add this account. If
                                        scanning isn&apos;t available, use the
                                        setup key instead. Then enter the
                                        current 6-digit code shown in your app.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                            <div className="space-y-3">
                                <div>
                                    <h3 className="font-semibold text-foreground">
                                        Scan with your app
                                    </h3>
                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Use your authenticator app camera to add
                                        this account instantly.
                                    </p>
                                </div>

                                <div className="flex aspect-square w-full items-center justify-center rounded-2xl border bg-white p-4 shadow-sm lg:max-w-[240px]">
                                    {twoFactorQrCodeDataUri ? (
                                        <img
                                            src={twoFactorQrCodeDataUri}
                                            alt="Two-factor setup QR code"
                                            className="size-full object-contain"
                                        />
                                    ) : (
                                        <div className="text-sm text-muted-foreground">
                                            QR code unavailable
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-5">
                                <div className="rounded-xl border bg-background p-4">
                                    <FieldGroup>
                                        <Field>
                                            <FieldLabel htmlFor="setup_key">
                                                Setup key
                                            </FieldLabel>
                                            <div className="flex items-center gap-2 rounded-xl border bg-muted/20 p-2">
                                                <div className="min-w-0 flex-1 rounded-lg bg-background px-4 py-3 font-mono text-sm text-foreground">
                                                    <span className="break-all">
                                                        {twoFactorSetupKey ??
                                                            'Setup key unavailable'}
                                                    </span>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon-sm"
                                                    disabled={
                                                        !twoFactorSetupKey
                                                    }
                                                    onClick={() => {
                                                        if (twoFactorSetupKey) {
                                                            void copy(
                                                                twoFactorSetupKey,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    {copiedSetupKey ? (
                                                        <CheckIcon className="size-4" />
                                                    ) : (
                                                        <CopyIcon className="size-4" />
                                                    )}
                                                </Button>
                                            </div>
                                            <FieldDescription>
                                                Use this if your authenticator
                                                app can&apos;t scan QR codes.
                                            </FieldDescription>
                                        </Field>
                                    </FieldGroup>
                                </div>

                                <form
                                    noValidate
                                    className="space-y-4 rounded-xl border bg-background p-4"
                                    onSubmit={handleConfirmSubmit}
                                >
                                    <div>
                                        <h3 className="font-semibold text-foreground">
                                            Confirm your code
                                        </h3>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            Enter the current 6-digit code shown
                                            in your authenticator app to finish
                                            setup.
                                        </p>
                                    </div>

                                    <FormErrorSummary
                                        errors={confirmForm.errors}
                                        minMessages={2}
                                    />

                                    <Field
                                        data-invalid={
                                            confirmForm.invalid('code') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="code">
                                            Authentication code
                                        </FieldLabel>
                                        <InputOTP
                                            id="code"
                                            name="code"
                                            size="comfortable"
                                            value={confirmForm.data.code}
                                            onChange={(value) =>
                                                confirmForm.setField(
                                                    'code',
                                                    value.replace(/\D/g, ''),
                                                )
                                            }
                                            onBlur={() =>
                                                confirmForm.touch('code')
                                            }
                                            aria-invalid={
                                                confirmForm.invalid('code') ||
                                                undefined
                                            }
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
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
                                        <FieldError>
                                            {confirmForm.error('code')}
                                        </FieldError>
                                    </Field>

                                    <div className="space-y-3">
                                        <Button
                                            type="submit"
                                            size="comfortable"
                                            className="w-full rounded-xl text-base font-semibold"
                                            disabled={confirmForm.processing}
                                        >
                                            Confirm & Enable
                                        </Button>
                                        <div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full border-destructive/40 text-destructive hover:bg-destructive/5 hover:text-destructive"
                                                onClick={() =>
                                                    setPasswordAction('disable')
                                                }
                                            >
                                                Cancel Setup
                                            </Button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </SectionCard>
                ) : null}

                {twoFactorEnabled ? (
                    <>
                        <SectionCard
                            title="Protection status"
                            description="Two-factor authentication is active on your account."
                            status={status}
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/15 px-4 py-3">
                                <div className="flex items-start gap-3">
                                    <div className="flex size-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                                        <SmartphoneIcon className="size-4.5" />
                                    </div>
                                    <div>
                                        <p className="font-medium text-foreground">
                                            Authenticator app connected
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            You&apos;ll be asked for a 6-digit
                                            verification code each time you sign
                                            in.
                                        </p>
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    className="border-destructive/40 text-destructive hover:bg-destructive/5 hover:text-destructive"
                                    onClick={() => setPasswordAction('disable')}
                                >
                                    <ShieldOffIcon data-icon="inline-start" />
                                    Disable Two-Factor Authentication
                                </Button>
                            </div>
                        </SectionCard>

                        <Card className="py-6">
                            <CardContent className="px-6">
                                <div className="flex flex-col gap-5">
                                    <div className="space-y-2">
                                        <h2 className="flex items-center gap-2 text-[1.05rem] font-semibold text-foreground">
                                            <KeyRoundIcon className="size-4.5 shrink-0" />
                                            <span>Recovery codes</span>
                                        </h2>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            Recovery codes are your backup way
                                            in. Generate a fresh set whenever
                                            you need and save them immediately.
                                        </p>
                                    </div>

                                    <Alert className="border-muted-foreground/20 bg-muted/20">
                                        <ShieldAlertIcon className="size-4" />
                                        <AlertDescription>
                                            Existing recovery codes are never
                                            shown again for security. Generating
                                            new ones will replace the old set.
                                        </AlertDescription>
                                    </Alert>

                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setPasswordAction('regenerate')
                                            }
                                        >
                                            Regenerate Recovery Codes
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                ) : null}
            </div>

            <PasswordActionDialog
                open={passwordAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPasswordAction(null);
                        passwordActionForm.reset();
                    }
                }}
                action={passwordAction}
                form={passwordActionForm}
                onSubmit={handlePasswordActionSubmit}
            />

            <RecoveryCodesDialog
                open={recoveryDialogOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setDismissedRecoveryCodesKey(recoveryCodesKey);
                    }
                }}
                codes={twoFactorRecoveryCodes}
            />
        </AppLayout>
    );
}
