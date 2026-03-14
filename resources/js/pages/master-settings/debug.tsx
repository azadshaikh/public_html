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
    { title: 'Master Settings', href: route('app.masters.settings.index') },
    { title: 'Debug', href: route('app.masters.settings.debug') },
];

type DebugPageProps = {
    settings: {
        enable_debugging: boolean;
        enable_debugging_bar: boolean;
        enable_html_minification: boolean;
    };
    settingsNav: SettingsNavItem[];
};

type DebugFormData = {
    enable_debugging: boolean;
    enable_debugging_bar: boolean;
    enable_html_minification: boolean;
};

export default function Debug({ settings, settingsNav }: DebugPageProps) {
    const form = useAppForm<DebugFormData>({
        defaults: {
            enable_debugging: settings.enable_debugging,
            enable_debugging_bar: settings.enable_debugging_bar,
            enable_html_minification: settings.enable_html_minification,
        },
        rememberKey: 'master-settings.debug',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.masters.settings.update', 'debug'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Debug settings updated',
                description:
                    'Your debug settings have been saved successfully.',
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
                            <CardTitle>Debug & Performance</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="enable_debugging">
                                                Enable Debugging
                                            </FieldLabel>
                                            <FieldDescription>
                                                Enable debug mode to display
                                                detailed error messages. Should
                                                be disabled in production.
                                            </FieldDescription>
                                        </div>
                                        <Switch
                                            id="enable_debugging"
                                            checked={form.data.enable_debugging}
                                            onCheckedChange={(checked) =>
                                                form.setField(
                                                    'enable_debugging',
                                                    checked === true,
                                                )
                                            }
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>

                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="enable_debugging_bar">
                                                Enable Debug Bar
                                            </FieldLabel>
                                            <FieldDescription>
                                                Show the debug bar for
                                                inspecting queries, requests,
                                                and performance metrics.
                                            </FieldDescription>
                                        </div>
                                        <Switch
                                            id="enable_debugging_bar"
                                            checked={
                                                form.data.enable_debugging_bar
                                            }
                                            onCheckedChange={(checked) =>
                                                form.setField(
                                                    'enable_debugging_bar',
                                                    checked === true,
                                                )
                                            }
                                            size="comfortable"
                                        />
                                    </div>
                                </Field>

                                <Field>
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="enable_html_minification">
                                                Enable HTML Minification
                                            </FieldLabel>
                                            <FieldDescription>
                                                Minify HTML output to reduce
                                                page size and improve load
                                                times.
                                            </FieldDescription>
                                        </div>
                                        <Switch
                                            id="enable_html_minification"
                                            checked={
                                                form.data
                                                    .enable_html_minification
                                            }
                                            onCheckedChange={(checked) =>
                                                form.setField(
                                                    'enable_html_minification',
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
