import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: route('app.settings.index') },
    { title: 'Development Mode', href: route('app.settings.development') },
];

type DevelopmentPageProps = {
    settings: {
        mode_enabled: boolean;
    };
    settingsNav: SettingsNavItem[];
};

type DevelopmentFormData = {
    mode_enabled: boolean;
};

export default function Development({
    settings,
    settingsNav,
}: DevelopmentPageProps) {
    const form = useAppForm<DevelopmentFormData>({
        defaults: {
            mode_enabled: settings.mode_enabled,
        },
        rememberKey: 'settings.development',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.settings.update', 'development'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Development settings updated',
                description:
                    'Your development mode settings have been saved successfully.',
            },
        });
    };

    return (
        <SettingsLayout
            settingsNav={settingsNav}
            breadcrumbs={breadcrumbs}
            title="Settings"
            description="Manage your application settings."
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
                            <CardTitle>Development Mode</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="mode_enabled">
                                                Enable Development Mode
                                            </FieldLabel>
                                            <FieldDescription>
                                                Enables development mode which
                                                disables CDN cache headers. This
                                                is useful during active
                                                development.
                                            </FieldDescription>
                                        </div>
                                        <Switch
                                            id="mode_enabled"
                                            checked={form.data.mode_enabled}
                                            onCheckedChange={(checked) =>
                                                form.setField(
                                                    'mode_enabled',
                                                    checked === true,
                                                )
                                            }
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>
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
            </div>
        </SettingsLayout>
    );
}
