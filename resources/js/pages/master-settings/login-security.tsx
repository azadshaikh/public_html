import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import MasterSettingsController from '@/actions/App/Http/Controllers/Masters/SettingsController';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Settings', href: MasterSettingsController.index() },
    { title: 'Login Security', href: MasterSettingsController.loginSecurity() },
];

type LoginSecurityPageProps = {
    settings: {
        admin_login_url_slug: string;
        limit_login_attempts_enabled: boolean;
        limit_login_attempts: string;
        lockout_time: string;
    };
    settingsNav: SettingsNavItem[];
};

type LoginSecurityFormData = {
    admin_login_url_slug: string;
    limit_login_attempts_enabled: boolean;
    limit_login_attempts: string;
    lockout_time: string;
};

export default function LoginSecurity({ settings, settingsNav }: LoginSecurityPageProps) {
    const form = useAppForm<LoginSecurityFormData>({
        defaults: {
            admin_login_url_slug: settings.admin_login_url_slug,
            limit_login_attempts_enabled: settings.limit_login_attempts_enabled,
            limit_login_attempts: settings.limit_login_attempts,
            lockout_time: settings.lockout_time,
        },
        rememberKey: 'master-settings.login-security',
        dirtyGuard: { enabled: true },
        rules: {
            admin_login_url_slug: [formValidators.required('Admin login URL slug')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(MasterSettingsController.update('login_security'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Login security settings updated',
                description: 'Your login security settings have been saved successfully.',
            },
        });
    };

    return (
        <SettingsLayout settingsNav={settingsNav} breadcrumbs={breadcrumbs} title="Master Settings" description="Manage platform-level configuration.">
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <form noValidate className="flex flex-col gap-6" onSubmit={handleSubmit}>
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Login Security</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('admin_login_url_slug') || undefined}>
                                    <FieldLabel htmlFor="admin_login_url_slug">
                                        Admin Login URL Slug <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <FieldDescription>
                                        The URL path used to access the admin login page (e.g., &quot;admin&quot;).
                                    </FieldDescription>
                                    <Input
                                        id="admin_login_url_slug"
                                        value={form.data.admin_login_url_slug}
                                        onChange={(e) => form.setField('admin_login_url_slug', e.target.value)}
                                        onBlur={() => form.touch('admin_login_url_slug')}
                                        aria-invalid={form.invalid('admin_login_url_slug') || undefined}
                                        placeholder="admin"
                                        size="comfortable"
                                        autoFocus
                                    />
                                    <FieldError>{form.error('admin_login_url_slug')}</FieldError>
                                </Field>

                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="limit_login_attempts_enabled">Limit Login Attempts</FieldLabel>
                                            <FieldDescription>Enable rate limiting on login attempts to prevent brute-force attacks.</FieldDescription>
                                        </div>
                                        <Switch
                                            id="limit_login_attempts_enabled"
                                            checked={form.data.limit_login_attempts_enabled}
                                            onCheckedChange={(checked) => form.setField('limit_login_attempts_enabled', checked === true)}
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>

                                {form.data.limit_login_attempts_enabled ? (
                                    <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                        <Field data-invalid={form.invalid('limit_login_attempts') || undefined}>
                                            <FieldLabel htmlFor="limit_login_attempts">Max Attempts</FieldLabel>
                                            <Input
                                                id="limit_login_attempts"
                                                type="number"
                                                min="1"
                                                value={form.data.limit_login_attempts}
                                                onChange={(e) => form.setField('limit_login_attempts', e.target.value)}
                                                onBlur={() => form.touch('limit_login_attempts')}
                                                aria-invalid={form.invalid('limit_login_attempts') || undefined}
                                                placeholder="5"
                                                size="comfortable"
                                            />
                                            <FieldError>{form.error('limit_login_attempts')}</FieldError>
                                        </Field>

                                        <Field data-invalid={form.invalid('lockout_time') || undefined}>
                                            <FieldLabel htmlFor="lockout_time">Lockout Time (seconds)</FieldLabel>
                                            <Input
                                                id="lockout_time"
                                                type="number"
                                                min="1"
                                                value={form.data.lockout_time}
                                                onChange={(e) => form.setField('lockout_time', e.target.value)}
                                                onBlur={() => form.touch('lockout_time')}
                                                aria-invalid={form.invalid('lockout_time') || undefined}
                                                placeholder="60"
                                                size="comfortable"
                                            />
                                            <FieldError>{form.error('lockout_time')}</FieldError>
                                        </Field>
                                    </FieldGroup>
                                ) : null}
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
