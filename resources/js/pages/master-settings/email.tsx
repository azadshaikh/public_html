import { useHttp } from '@inertiajs/react';
import { SaveIcon, SendIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem, SelectOption, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Settings', href: route('app.masters.settings.index') },
    { title: 'Email', href: route('app.masters.settings.email') },
];

type EmailPageProps = {
    settings: {
        email_driver: string;
        email_host: string;
        email_port: string;
        email_username: string;
        email_password: string;
        email_encryption: string;
        email_from_address: string;
        email_from_name: string;
    };
    secretState: {
        hasEmailPassword: boolean;
    };
    options: {
        emailDrivers: SelectOption[];
        securityTypes: SelectOption[];
    };
    settingsNav: SettingsNavItem[];
};

type EmailFormData = {
    email_driver: string;
    email_host: string;
    email_port: string;
    email_username: string;
    email_password: string;
    clear_email_password: boolean;
    email_encryption: string;
    email_from_address: string;
    email_from_name: string;
};

export default function Email({
    settings,
    secretState,
    options,
    settingsNav,
}: EmailPageProps) {
    const [testEmail, setTestEmail] = useState('');
    const [isSendingTest, setIsSendingTest] = useState(false);
    const testEmailRequest = useHttp<
        EmailFormData & { email: string },
        { success?: boolean; message?: string }
    >({
        email: '',
        clear_email_password: false,
        ...settings,
    });

    const form = useAppForm<EmailFormData>({
        defaults: {
            email_driver: settings.email_driver,
            email_host: settings.email_host,
            email_port: settings.email_port,
            email_username: settings.email_username,
            email_password: settings.email_password,
            clear_email_password: false,
            email_encryption: settings.email_encryption,
            email_from_address: settings.email_from_address,
            email_from_name: settings.email_from_name,
        },
        dontRemember: ['email_password'],
        rememberKey: 'master-settings.email',
        dirtyGuard: { enabled: true },
        rules: {
            email_from_address: [
                formValidators.required('From address'),
                formValidators.email('From address'),
            ],
            email_from_name: [formValidators.required('From name')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.masters.settings.update', 'email'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Email settings updated',
                description:
                    'Your email settings have been saved successfully.',
            },
        });
    };

    const handleSendTestEmail = async () => {
        if (!testEmail) {
            showAppToast({
                variant: 'error',
                title: 'Email required',
                description: 'Please enter a recipient email address.',
            });
            return;
        }

        setIsSendingTest(true);

        try {
            testEmailRequest.transform(() => ({
                email: testEmail,
                ...form.data,
            }));

            const data = await testEmailRequest.post(
                route('app.masters.settings.send-test-mail'),
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            showAppToast({
                variant: data.success ? 'success' : 'error',
                title: data.success ? 'Test email sent' : 'Test email failed',
                description: data.message,
            });
        } catch {
            showAppToast({
                variant: 'error',
                title: 'Test email failed',
                description:
                    'An unexpected error occurred while sending the test email.',
            });
        } finally {
            setIsSendingTest(false);
        }
    };

    const isSmtp = form.data.email_driver === 'smtp';

    return (
        <SettingsLayout
            settingsNav={settingsNav}
            breadcrumbs={breadcrumbs}
            title="Master Settings"
            description="Manage platform-level configuration."
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <form
                    noValidate
                    className="flex flex-col gap-6"
                    onSubmit={handleSubmit}
                >
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Email Configuration</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('email_driver') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="email_driver">
                                        Email Driver
                                    </FieldLabel>
                                    <NativeSelect
                                        id="email_driver"
                                        className="w-full"
                                        size="comfortable"
                                        value={form.data.email_driver}
                                        onChange={(e) =>
                                            form.setField(
                                                'email_driver',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('email_driver')
                                        }
                                        aria-invalid={
                                            form.invalid('email_driver') ||
                                            undefined
                                        }
                                    >
                                        {options.emailDrivers.map((opt) => (
                                            <option
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('email_driver')}
                                    </FieldError>
                                </Field>

                                {isSmtp ? (
                                    <>
                                        <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                            <Field
                                                data-invalid={
                                                    form.invalid(
                                                        'email_host',
                                                    ) || undefined
                                                }
                                            >
                                                <FieldLabel htmlFor="email_host">
                                                    SMTP Host
                                                </FieldLabel>
                                                <Input
                                                    id="email_host"
                                                    value={form.data.email_host}
                                                    onChange={(e) =>
                                                        form.setField(
                                                            'email_host',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onBlur={() =>
                                                        form.touch('email_host')
                                                    }
                                                    aria-invalid={
                                                        form.invalid(
                                                            'email_host',
                                                        ) || undefined
                                                    }
                                                    placeholder="smtp.example.com"
                                                    size="comfortable"
                                                />
                                                <FieldError>
                                                    {form.error('email_host')}
                                                </FieldError>
                                            </Field>

                                            <Field
                                                data-invalid={
                                                    form.invalid(
                                                        'email_port',
                                                    ) || undefined
                                                }
                                            >
                                                <FieldLabel htmlFor="email_port">
                                                    SMTP Port
                                                </FieldLabel>
                                                <Input
                                                    id="email_port"
                                                    value={form.data.email_port}
                                                    onChange={(e) =>
                                                        form.setField(
                                                            'email_port',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onBlur={() =>
                                                        form.touch('email_port')
                                                    }
                                                    aria-invalid={
                                                        form.invalid(
                                                            'email_port',
                                                        ) || undefined
                                                    }
                                                    placeholder="587"
                                                    size="comfortable"
                                                />
                                                <FieldError>
                                                    {form.error('email_port')}
                                                </FieldError>
                                            </Field>
                                        </FieldGroup>

                                        <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                            <Field
                                                data-invalid={
                                                    form.invalid(
                                                        'email_username',
                                                    ) || undefined
                                                }
                                            >
                                                <FieldLabel htmlFor="email_username">
                                                    SMTP Username
                                                </FieldLabel>
                                                <Input
                                                    id="email_username"
                                                    value={
                                                        form.data.email_username
                                                    }
                                                    onChange={(e) =>
                                                        form.setField(
                                                            'email_username',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onBlur={() =>
                                                        form.touch(
                                                            'email_username',
                                                        )
                                                    }
                                                    aria-invalid={
                                                        form.invalid(
                                                            'email_username',
                                                        ) || undefined
                                                    }
                                                    placeholder="Enter SMTP username"
                                                    size="comfortable"
                                                />
                                                <FieldError>
                                                    {form.error(
                                                        'email_username',
                                                    )}
                                                </FieldError>
                                            </Field>

                                            <Field
                                                data-invalid={
                                                    form.invalid(
                                                        'email_password',
                                                    ) || undefined
                                                }
                                            >
                                                <FieldLabel htmlFor="email_password">
                                                    SMTP Password
                                                </FieldLabel>
                                                <Input
                                                    id="email_password"
                                                    type="password"
                                                    value={
                                                        form.data.email_password
                                                    }
                                                    onChange={(e) => {
                                                        form.setField(
                                                            'email_password',
                                                            e.target.value,
                                                        );

                                                        if (
                                                            e.target.value !==
                                                            ''
                                                        ) {
                                                            form.setField(
                                                                'clear_email_password',
                                                                false,
                                                            );
                                                        }
                                                    }}
                                                    onBlur={() =>
                                                        form.touch(
                                                            'email_password',
                                                        )
                                                    }
                                                    aria-invalid={
                                                        form.invalid(
                                                            'email_password',
                                                        ) || undefined
                                                    }
                                                    placeholder="Enter SMTP password"
                                                    size="comfortable"
                                                />
                                                {secretState.hasEmailPassword ? (
                                                    <div className="mt-3 flex items-start gap-3 rounded-lg border border-dashed border-border px-3 py-3">
                                                        <Checkbox
                                                            id="clear_email_password"
                                                            checked={
                                                                form.data
                                                                    .clear_email_password
                                                            }
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                form.setField(
                                                                    'clear_email_password',
                                                                    checked ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                        <div className="space-y-1">
                                                            <FieldLabel htmlFor="clear_email_password">
                                                                Clear saved SMTP
                                                                password on save
                                                            </FieldLabel>
                                                            <FieldDescription>
                                                                Leave the password
                                                                field blank to keep
                                                                the current value,
                                                                or check this to
                                                                remove it.
                                                            </FieldDescription>
                                                        </div>
                                                    </div>
                                                ) : null}
                                                <FieldError>
                                                    {form.error(
                                                        'email_password',
                                                    )}
                                                </FieldError>
                                            </Field>
                                        </FieldGroup>

                                        <Field
                                            data-invalid={
                                                form.invalid(
                                                    'email_encryption',
                                                ) || undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="email_encryption">
                                                Encryption
                                            </FieldLabel>
                                            <NativeSelect
                                                id="email_encryption"
                                                className="w-full"
                                                size="comfortable"
                                                value={
                                                    form.data.email_encryption
                                                }
                                                onChange={(e) =>
                                                    form.setField(
                                                        'email_encryption',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch(
                                                        'email_encryption',
                                                    )
                                                }
                                                aria-invalid={
                                                    form.invalid(
                                                        'email_encryption',
                                                    ) || undefined
                                                }
                                            >
                                                {options.securityTypes.map(
                                                    (opt) => (
                                                        <option
                                                            key={opt.value}
                                                            value={opt.value}
                                                        >
                                                            {opt.label}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                            <FieldError>
                                                {form.error('email_encryption')}
                                            </FieldError>
                                        </Field>
                                    </>
                                ) : null}

                                <Separator />

                                <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                    <Field
                                        data-invalid={
                                            form.invalid('email_from_name') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="email_from_name">
                                            From Name{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <Input
                                            id="email_from_name"
                                            value={form.data.email_from_name}
                                            onChange={(e) =>
                                                form.setField(
                                                    'email_from_name',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('email_from_name')
                                            }
                                            aria-invalid={
                                                form.invalid(
                                                    'email_from_name',
                                                ) || undefined
                                            }
                                            placeholder="Your App Name"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('email_from_name')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid(
                                                'email_from_address',
                                            ) || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="email_from_address">
                                            From Address{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <Input
                                            id="email_from_address"
                                            type="email"
                                            value={form.data.email_from_address}
                                            onChange={(e) =>
                                                form.setField(
                                                    'email_from_address',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('email_from_address')
                                            }
                                            aria-invalid={
                                                form.invalid(
                                                    'email_from_address',
                                                ) || undefined
                                            }
                                            placeholder="noreply@example.com"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('email_from_address')}
                                        </FieldError>
                                    </Field>
                                </FieldGroup>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <SaveIcon data-icon="inline-start" />
                        )}
                        {form.processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>Send Test Email</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <FieldGroup>
                            <FieldDescription>
                                Send a test email using the current
                                configuration to verify it works correctly.
                            </FieldDescription>

                            <Field>
                                <FieldLabel htmlFor="test_email">
                                    Recipient Email
                                </FieldLabel>
                                <div className="flex gap-3">
                                    <Input
                                        id="test_email"
                                        type="email"
                                        value={testEmail}
                                        onChange={(e) =>
                                            setTestEmail(e.target.value)
                                        }
                                        placeholder="test@example.com"
                                        size="comfortable"
                                        className="flex-1"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleSendTestEmail}
                                        disabled={isSendingTest}
                                    >
                                        {isSendingTest ? (
                                            <Spinner />
                                        ) : (
                                            <SendIcon data-icon="inline-start" />
                                        )}
                                        {isSendingTest ? 'Sending...' : 'Send'}
                                    </Button>
                                </div>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
