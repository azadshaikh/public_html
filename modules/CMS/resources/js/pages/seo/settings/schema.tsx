import { BracesIcon, SaveIcon, WaypointsIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { getSeoSettingsBreadcrumbs, getSeoSettingsNav } from '../../../lib/seo-settings';
import type { SchemaFormValues, SchemaPageProps } from '../../../types/seo';

export default function SeoSchemaPage({ initialValues }: SchemaPageProps) {
    const form = useAppForm<SchemaFormValues>({
        defaults: initialValues,
        rememberKey: 'seo.settings.schema',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('seo.settings.schema.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Schema settings updated',
                description: 'Structured data defaults were saved successfully.',
            },
        });
    };

    return (
        <SettingsLayout
            settingsNav={getSeoSettingsNav()}
            breadcrumbs={getSeoSettingsBreadcrumbs('Schema')}
            title="Schema"
            description="Choose which site-wide structured data enhancements are enabled by default."
            activeSlug="schema"
            railLabel="SEO settings"
        >
            <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <Alert>
                    <BracesIcon className="size-4" />
                    <AlertTitle>Structured data at a glance</AlertTitle>
                    <AlertDescription>
                        Schema improves how search engines interpret your content and can unlock richer result types.
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <WaypointsIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Site-wide schema defaults</CardTitle>
                        </div>
                        <CardDescription>
                            Turn structured data features on or off for the entire site.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <Field orientation="horizontal">
                            <Switch
                                checked={form.data.enable_article_schema}
                                onCheckedChange={(checked) =>
                                    form.setField('enable_article_schema', checked)
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <FieldLabel>Enable article schema</FieldLabel>
                                <FieldDescription>
                                    Adds article-specific structured data to blog content for better rich result eligibility.
                                </FieldDescription>
                            </div>
                        </Field>

                        <Field orientation="horizontal">
                            <Switch
                                checked={form.data.enable_breadcrumb_schema}
                                onCheckedChange={(checked) =>
                                    form.setField('enable_breadcrumb_schema', checked)
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <FieldLabel>Enable breadcrumb schema</FieldLabel>
                                <FieldDescription>
                                    Helps search engines understand hierarchy and can display breadcrumb paths in results.
                                </FieldDescription>
                            </div>
                        </Field>
                    </CardContent>
                    <CardFooter className="justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <Spinner className="mr-2 size-4" />
                            ) : (
                                <SaveIcon data-icon="inline-start" />
                            )}
                            Save schema settings
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </SettingsLayout>
    );
}
