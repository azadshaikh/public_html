import { SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Settings', href: route('app.masters.settings.index') },
    { title: 'App Settings', href: route('app.masters.settings.app') },
];

type AppSettingsPageProps = {
    settings: {
        homepage_redirect_enabled: boolean;
        homepage_redirect_slug: string;
    };
    cmsEnabled: boolean;
    settingsNav: SettingsNavItem[];
};

type AppSettingsFormData = {
    homepage_redirect_enabled: boolean;
    homepage_redirect_slug: string;
};

export default function AppSettings({
    settings,
    cmsEnabled,
    settingsNav,
}: AppSettingsPageProps) {
    const form = useAppForm<AppSettingsFormData>({
        defaults: {
            homepage_redirect_enabled: settings.homepage_redirect_enabled,
            homepage_redirect_slug: settings.homepage_redirect_slug,
        },
        rememberKey: 'master-settings.app',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.masters.settings.update', 'app'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'App settings updated',
                description: 'Your app settings have been saved successfully.',
            },
        });
    };

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
                            <CardTitle>App Settings</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                {cmsEnabled ? (
                                    <>
                                        <Field>
                                            <div className="flex items-center justify-between gap-4">
                                                <div className="space-y-1">
                                                    <FieldLabel htmlFor="homepage_redirect_enabled">
                                                        Homepage Redirect
                                                    </FieldLabel>
                                                    <FieldDescription>
                                                        Redirect the homepage to
                                                        a specific page slug.
                                                    </FieldDescription>
                                                </div>
                                                <Switch
                                                    id="homepage_redirect_enabled"
                                                    checked={
                                                        form.data
                                                            .homepage_redirect_enabled
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        form.setField(
                                                            'homepage_redirect_enabled',
                                                            checked === true,
                                                        )
                                                    }
                                                    size="comfortable"
                                                />
                                            </div>
                                        </Field>

                                        {form.data.homepage_redirect_enabled ? (
                                            <Field
                                                data-invalid={
                                                    form.invalid(
                                                        'homepage_redirect_slug',
                                                    ) || undefined
                                                }
                                            >
                                                <FieldLabel htmlFor="homepage_redirect_slug">
                                                    Redirect Slug
                                                </FieldLabel>
                                                <FieldDescription>
                                                    The page slug to redirect
                                                    the homepage to (e.g.,
                                                    &quot;welcome&quot;).
                                                </FieldDescription>
                                                <Input
                                                    id="homepage_redirect_slug"
                                                    value={
                                                        form.data
                                                            .homepage_redirect_slug
                                                    }
                                                    onChange={(e) =>
                                                        form.setField(
                                                            'homepage_redirect_slug',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onBlur={() =>
                                                        form.touch(
                                                            'homepage_redirect_slug',
                                                        )
                                                    }
                                                    aria-invalid={
                                                        form.invalid(
                                                            'homepage_redirect_slug',
                                                        ) || undefined
                                                    }
                                                    placeholder="Enter page slug"
                                                    size="comfortable"
                                                />
                                                <FieldError>
                                                    {form.error(
                                                        'homepage_redirect_slug',
                                                    )}
                                                </FieldError>
                                            </Field>
                                        ) : null}
                                    </>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Homepage redirect settings are available
                                        when the CMS module is enabled.
                                    </p>
                                )}
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {cmsEnabled ? (
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <SaveIcon data-icon="inline-start" />
                            )}
                            {form.processing ? 'Saving...' : 'Save Settings'}
                        </Button>
                    ) : null}
                </form>
            </div>
        </SettingsLayout>
    );
}
