import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: route('app.settings.index') },
    { title: 'General', href: route('app.settings.general') },
];

type GeneralPageProps = {
    settings: {
        site_title: string;
        tagline: string;
    };
    settingsNav: SettingsNavItem[];
};

type GeneralFormData = {
    site_title: string;
    tagline: string;
};

export default function General({ settings, settingsNav }: GeneralPageProps) {
    const form = useAppForm<GeneralFormData>({
        defaults: {
            site_title: settings.site_title,
            tagline: settings.tagline,
        },
        rememberKey: 'settings.general',
        dirtyGuard: { enabled: true },
        rules: {
            site_title: [formValidators.required('Site title')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.settings.update', 'general'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'General settings updated',
                description: 'Your general settings have been saved successfully.',
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
                            <CardTitle>General</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('site_title') || undefined}>
                                    <FieldLabel htmlFor="site_title">
                                        Site Title <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <Input
                                        id="site_title"
                                        value={form.data.site_title}
                                        onChange={(e) => form.setField('site_title', e.target.value)}
                                        onBlur={() => form.touch('site_title')}
                                        aria-invalid={form.invalid('site_title') || undefined}
                                        placeholder="Enter site title"
                                        size="comfortable"
                                        autoFocus
                                    />
                                    <FieldError>{form.error('site_title')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('tagline') || undefined}>
                                    <FieldLabel htmlFor="tagline">Tagline</FieldLabel>
                                    <Input
                                        id="tagline"
                                        value={form.data.tagline}
                                        onChange={(e) => form.setField('tagline', e.target.value)}
                                        onBlur={() => form.touch('tagline')}
                                        aria-invalid={form.invalid('tagline') || undefined}
                                        placeholder="Enter site tagline"
                                        size="comfortable"
                                    />
                                    <FieldError>{form.error('tagline')}</FieldError>
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
