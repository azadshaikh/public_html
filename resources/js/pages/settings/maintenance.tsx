import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: route('app.settings.index') },
    { title: 'Maintenance Mode', href: route('app.settings.maintenance') },
];

type MaintenancePageProps = {
    settings: {
        mode_enabled: boolean;
        maintenance_mode_type: string;
        title: string;
        message: string;
    };
    settingsNav: SettingsNavItem[];
};

type MaintenanceFormData = {
    mode_enabled: boolean;
    maintenance_mode_type: string;
    title: string;
    message: string;
};

const maintenanceModeTypes = [
    { value: 'frontend', label: 'Frontend Only' },
    { value: 'full', label: 'Full Application' },
];

export default function Maintenance({ settings, settingsNav }: MaintenancePageProps) {
    const form = useAppForm<MaintenanceFormData>({
        defaults: {
            mode_enabled: settings.mode_enabled,
            maintenance_mode_type: settings.maintenance_mode_type,
            title: settings.title,
            message: settings.message,
        },
        rememberKey: 'settings.maintenance',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.settings.update', 'maintenance'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Maintenance settings updated',
                description: 'Your maintenance mode settings have been saved successfully.',
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
                            <CardTitle>Maintenance Mode</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="mode_enabled">Enable Maintenance Mode</FieldLabel>
                                            <FieldDescription>Put the site into maintenance mode. Visitors will see a maintenance page.</FieldDescription>
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
                                        <Field data-invalid={form.invalid('maintenance_mode_type') || undefined}>
                                            <FieldLabel htmlFor="maintenance_mode_type">Maintenance Type</FieldLabel>
                                            <FieldDescription>Choose whether to show maintenance for the frontend only or the entire application.</FieldDescription>
                                            <NativeSelect
                                                id="maintenance_mode_type"
                                                className="w-full"
                                                size="comfortable"
                                                value={form.data.maintenance_mode_type}
                                                onChange={(e) => form.setField('maintenance_mode_type', e.target.value)}
                                                onBlur={() => form.touch('maintenance_mode_type')}
                                                aria-invalid={form.invalid('maintenance_mode_type') || undefined}
                                            >
                                                {maintenanceModeTypes.map((opt) => (
                                                    <option key={opt.value} value={opt.value}>
                                                        {opt.label}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                            <FieldError>{form.error('maintenance_mode_type')}</FieldError>
                                        </Field>

                                        <Field data-invalid={form.invalid('title') || undefined}>
                                            <FieldLabel htmlFor="title">Title</FieldLabel>
                                            <Input
                                                id="title"
                                                value={form.data.title}
                                                onChange={(e) => form.setField('title', e.target.value)}
                                                onBlur={() => form.touch('title')}
                                                aria-invalid={form.invalid('title') || undefined}
                                                placeholder="Maintenance in progress"
                                                size="comfortable"
                                            />
                                            <FieldError>{form.error('title')}</FieldError>
                                        </Field>

                                        <Field data-invalid={form.invalid('message') || undefined}>
                                            <FieldLabel htmlFor="message">Message</FieldLabel>
                                            <Textarea
                                                id="message"
                                                value={form.data.message}
                                                onChange={(e) => form.setField('message', e.target.value)}
                                                onBlur={() => form.touch('message')}
                                                aria-invalid={form.invalid('message') || undefined}
                                                placeholder="We are currently performing scheduled maintenance..."
                                                rows={3}
                                            />
                                            <FieldError>{form.error('message')}</FieldError>
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
