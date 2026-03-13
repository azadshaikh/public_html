import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SelectOption, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: SettingsController.index() },
    { title: 'Registration', href: SettingsController.registration() },
];

type RegistrationPageProps = {
    settings: {
        enable_registration: boolean;
        default_role: string;
        require_email_verification: boolean;
        auto_approve: boolean;
    };
    options: {
        roles: SelectOption[];
    };
    settingsNav: SettingsNavItem[];
};

type RegistrationFormData = {
    enable_registration: boolean;
    default_role: string;
    require_email_verification: boolean;
    auto_approve: boolean;
};

export default function Registration({ settings, options, settingsNav }: RegistrationPageProps) {
    const form = useAppForm<RegistrationFormData>({
        defaults: {
            enable_registration: settings.enable_registration,
            default_role: settings.default_role,
            require_email_verification: settings.require_email_verification,
            auto_approve: settings.auto_approve,
        },
        rememberKey: 'settings.registration',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(SettingsController.update('registration'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Registration settings updated',
                description: 'Your registration settings have been saved successfully.',
            },
        });
    };

    return (
        <SettingsLayout settingsNav={settingsNav} breadcrumbs={breadcrumbs} title="Settings" description="Manage your application settings.">
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <form noValidate className="flex flex-col gap-6" onSubmit={handleSubmit}>
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Registration</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="enable_registration">Enable Registration</FieldLabel>
                                            <FieldDescription>Allow new users to register on the application.</FieldDescription>
                                        </div>
                                        <Switch
                                            id="enable_registration"
                                            checked={form.data.enable_registration}
                                            onCheckedChange={(checked) => form.setField('enable_registration', checked === true)}
                                            size="comfortable"
                                        />
                                    </div>
                                    <FieldError>{form.error('enable_registration')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('default_role') || undefined}>
                                    <FieldLabel htmlFor="default_role">Default Role</FieldLabel>
                                    <FieldDescription>The role assigned to newly registered users.</FieldDescription>
                                    <NativeSelect
                                        id="default_role"
                                        className="w-full"
                                        size="comfortable"
                                        value={form.data.default_role}
                                        onChange={(e) => form.setField('default_role', e.target.value)}
                                        onBlur={() => form.touch('default_role')}
                                        aria-invalid={form.invalid('default_role') || undefined}
                                    >
                                        <option value="">Select a role</option>
                                        {options.roles.map((opt) => (
                                            <option key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.error('default_role')}</FieldError>
                                </Field>

                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="require_email_verification">Require Email Verification</FieldLabel>
                                            <FieldDescription>Users must verify their email address before accessing the application.</FieldDescription>
                                        </div>
                                        <Switch
                                            id="require_email_verification"
                                            checked={form.data.require_email_verification}
                                            onCheckedChange={(checked) => form.setField('require_email_verification', checked === true)}
                                            size="comfortable"
                                        />
                                    </div>
                                    <FieldError>{form.error('require_email_verification')}</FieldError>
                                </Field>

                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="auto_approve">Auto Approve Users</FieldLabel>
                                            <FieldDescription>Automatically approve new user registrations without manual review.</FieldDescription>
                                        </div>
                                        <Switch
                                            id="auto_approve"
                                            checked={form.data.auto_approve}
                                            onCheckedChange={(checked) => form.setField('auto_approve', checked === true)}
                                            size="comfortable"
                                        />
                                    </div>
                                    <FieldError>{form.error('auto_approve')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <Spinner /> : <SaveIcon data-icon="inline-start" />}
                        {form.processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    );
}
