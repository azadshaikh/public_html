import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: route('app.settings.index') },
    { title: 'Site Access Protection', href: route('app.settings.site-access-protection') },
];

type SiteAccessProtectionPageProps = {
    settings: {
        mode_enabled: boolean;
        password: string;
        protection_message: string;
    };
    settingsNav: SettingsNavItem[];
};

type SiteAccessProtectionFormData = {
    mode_enabled: boolean;
    password: string;
    protection_message: string;
};

export default function SiteAccessProtection({ settings, settingsNav }: SiteAccessProtectionPageProps) {
    const form = useAppForm<SiteAccessProtectionFormData>({
        defaults: {
            mode_enabled: settings.mode_enabled,
            password: settings.password,
            protection_message: settings.protection_message,
        },
        rememberKey: 'settings.site-access-protection',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.settings.update', 'site_access_protection'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Site access protection settings updated',
                description: 'Your site access protection settings have been saved successfully.',
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
                            <CardTitle>Site Access Protection</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="mode_enabled">Enable Site Access Protection</FieldLabel>
                                            <FieldDescription>Require a password to access the public-facing site.</FieldDescription>
                                        </div>
                                        <Switch
                                            id="mode_enabled"
                                            checked={form.data.mode_enabled}
                                            onCheckedChange={(checked) => form.setField('mode_enabled', checked === true)}
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>

                                {form.data.mode_enabled ? (
                                    <>
                                        <Field data-invalid={form.invalid('password') || undefined}>
                                            <FieldLabel htmlFor="password">Protection Password</FieldLabel>
                                            <Input
                                                id="password"
                                                type="password"
                                                value={form.data.password}
                                                onChange={(e) => form.setField('password', e.target.value)}
                                                onBlur={() => form.touch('password')}
                                                aria-invalid={form.invalid('password') || undefined}
                                                placeholder="Enter access password"
                                                size="comfortable"
                                            />
                                            <FieldError>{form.error('password')}</FieldError>
                                        </Field>

                                        <Field data-invalid={form.invalid('protection_message') || undefined}>
                                            <FieldLabel htmlFor="protection_message">Protection Message</FieldLabel>
                                            <FieldDescription>Message displayed to visitors on the password page.</FieldDescription>
                                            <Textarea
                                                id="protection_message"
                                                value={form.data.protection_message}
                                                onChange={(e) => form.setField('protection_message', e.target.value)}
                                                onBlur={() => form.touch('protection_message')}
                                                aria-invalid={form.invalid('protection_message') || undefined}
                                                placeholder="This site is currently protected..."
                                                rows={3}
                                            />
                                            <FieldError>{form.error('protection_message')}</FieldError>
                                        </Field>
                                    </>
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
