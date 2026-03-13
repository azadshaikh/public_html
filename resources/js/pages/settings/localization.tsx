import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem, SelectOption, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: SettingsController.index() },
    { title: 'Localization', href: SettingsController.localization() },
];

type LocalizationPageProps = {
    settings: {
        language: string;
        date_format: string;
        time_format: string;
        timezone: string;
    };
    options: {
        languages: SelectOption[];
        dateFormats: SelectOption[];
        timeFormats: SelectOption[];
        timezones: SelectOption[];
    };
    settingsNav: SettingsNavItem[];
};

type LocalizationFormData = {
    language: string;
    date_format: string;
    time_format: string;
    timezone: string;
};

export default function Localization({ settings, options, settingsNav }: LocalizationPageProps) {
    const form = useAppForm<LocalizationFormData>({
        defaults: {
            language: settings.language,
            date_format: settings.date_format,
            time_format: settings.time_format,
            timezone: settings.timezone,
        },
        rememberKey: 'settings.localization',
        dirtyGuard: { enabled: true },
        rules: {
            language: [formValidators.required('Language')],
            date_format: [formValidators.required('Date format')],
            time_format: [formValidators.required('Time format')],
            timezone: [formValidators.required('Timezone')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(SettingsController.update('localization'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Localization settings updated',
                description: 'Your localization settings have been saved successfully.',
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
                            <CardTitle>Localization</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('language') || undefined}>
                                    <FieldLabel htmlFor="language">Language</FieldLabel>
                                    <NativeSelect
                                        id="language"
                                        className="w-full"
                                        size="comfortable"
                                        value={form.data.language}
                                        onChange={(e) => form.setField('language', e.target.value)}
                                        onBlur={() => form.touch('language')}
                                        aria-invalid={form.invalid('language') || undefined}
                                    >
                                        {options.languages.map((opt) => (
                                            <option key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.error('language')}</FieldError>
                                </Field>

                                <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                    <Field data-invalid={form.invalid('date_format') || undefined}>
                                        <FieldLabel htmlFor="date_format">Date Format</FieldLabel>
                                        <NativeSelect
                                            id="date_format"
                                            className="w-full"
                                            size="comfortable"
                                            value={form.data.date_format}
                                            onChange={(e) => form.setField('date_format', e.target.value)}
                                            onBlur={() => form.touch('date_format')}
                                            aria-invalid={form.invalid('date_format') || undefined}
                                        >
                                            {options.dateFormats.map((opt) => (
                                                <option key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        <FieldError>{form.error('date_format')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('time_format') || undefined}>
                                        <FieldLabel htmlFor="time_format">Time Format</FieldLabel>
                                        <NativeSelect
                                            id="time_format"
                                            className="w-full"
                                            size="comfortable"
                                            value={form.data.time_format}
                                            onChange={(e) => form.setField('time_format', e.target.value)}
                                            onBlur={() => form.touch('time_format')}
                                            aria-invalid={form.invalid('time_format') || undefined}
                                        >
                                            {options.timeFormats.map((opt) => (
                                                <option key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        <FieldError>{form.error('time_format')}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <Field data-invalid={form.invalid('timezone') || undefined}>
                                    <FieldLabel htmlFor="timezone">Timezone</FieldLabel>
                                    <NativeSelect
                                        id="timezone"
                                        className="w-full"
                                        size="comfortable"
                                        value={form.data.timezone}
                                        onChange={(e) => form.setField('timezone', e.target.value)}
                                        onBlur={() => form.touch('timezone')}
                                        aria-invalid={form.invalid('timezone') || undefined}
                                    >
                                        {options.timezones.map((opt) => (
                                            <option key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.error('timezone')}</FieldError>
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
