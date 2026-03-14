import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: route('app.settings.index') },
    { title: 'Coming Soon Mode', href: route('app.settings.coming-soon') },
];

type ComingSoonPageProps = {
    settings: {
        enabled: boolean;
        description: string;
    };
    settingsNav: SettingsNavItem[];
};

type ComingSoonFormData = {
    enabled: boolean;
    description: string;
};

export default function ComingSoon({ settings, settingsNav }: ComingSoonPageProps) {
    const form = useAppForm<ComingSoonFormData>({
        defaults: {
            enabled: settings.enabled,
            description: settings.description,
        },
        rememberKey: 'settings.coming-soon',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.settings.update', 'coming_soon'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Coming soon settings updated',
                description: 'Your coming soon mode settings have been saved successfully.',
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
                            <CardTitle>Coming Soon Mode</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="enabled">Enable Coming Soon Mode</FieldLabel>
                                            <FieldDescription>Display a coming soon page to visitors while you prepare your site.</FieldDescription>
                                        </div>
                                        <Switch
                                            id="enabled"
                                            checked={form.data.enabled}
                                            onCheckedChange={(checked) => form.setField('enabled', checked === true)}
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>

                                {form.data.enabled ? (
                                    <Field data-invalid={form.invalid('description') || undefined}>
                                        <FieldLabel htmlFor="description">Description</FieldLabel>
                                        <FieldDescription>Message displayed on the coming soon page.</FieldDescription>
                                        <Textarea
                                            id="description"
                                            value={form.data.description}
                                            onChange={(e) => form.setField('description', e.target.value)}
                                            onBlur={() => form.touch('description')}
                                            aria-invalid={form.invalid('description') || undefined}
                                            placeholder="We're working on something exciting..."
                                            rows={3}
                                        />
                                        <FieldError>{form.error('description')}</FieldError>
                                    </Field>
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
