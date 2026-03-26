import { ExternalLinkIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import SeoSettingsShell from '../../../components/seo-settings-shell';
import { getSeoSettingsBreadcrumbs } from '../../../lib/seo-settings';
import type { RobotsFormValues, RobotsPageProps } from '../../../types/seo';

export default function SeoRobotsPage({
    initialValues,
    robotsUrl,
    sitemapUrl,
}: RobotsPageProps) {
    const form = useAppForm<RobotsFormValues>({
        defaults: initialValues,
        rememberKey: 'seo.settings.robots',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('seo.settings.robots.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Robots.txt updated',
                description: 'Crawler rules were saved successfully.',
            },
        });
    };

    return (
        <SeoSettingsShell
            breadcrumbs={getSeoSettingsBreadcrumbs('Robots.txt')}
            title="Robots.txt"
        >
            <form
                className="flex flex-col gap-6"
                onSubmit={handleSubmit}
                noValidate
            >
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <Card>
                    <CardHeader>
                        <CardTitle>Robots directives</CardTitle>
                        <CardDescription>
                            Add or update crawler instructions. Leave the field
                            empty to remove the custom file.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        <Field
                            data-invalid={
                                form.invalid('robots_txt') || undefined
                            }
                        >
                            <FieldLabel htmlFor="robots_txt">
                                File contents
                            </FieldLabel>
                            <Textarea
                                id="robots_txt"
                                rows={18}
                                value={form.data.robots_txt}
                                onChange={(event) =>
                                    form.setField(
                                        'robots_txt',
                                        event.target.value,
                                    )
                                }
                                onBlur={() => form.touch('robots_txt')}
                                aria-invalid={
                                    form.invalid('robots_txt') || undefined
                                }
                                spellCheck={false}
                                autoCorrect="off"
                                autoCapitalize="off"
                                className="font-mono text-sm leading-6"
                                placeholder={`User-agent: *\nAllow: /\n\nSitemap: ${sitemapUrl}`}
                            />
                            <FieldDescription>
                                Example:{' '}
                                <span className="font-mono">
                                    Sitemap: {sitemapUrl}
                                </span>
                            </FieldDescription>
                            <FieldError>{form.error('robots_txt')}</FieldError>
                        </Field>
                    </CardContent>
                    <CardFooter className="flex flex-col justify-between gap-3 sm:flex-row">
                        <Button type="button" variant="outline" asChild>
                            <a
                                href={robotsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                View current robots.txt
                            </a>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <Spinner className="mr-2 size-4" />
                            ) : (
                                <SaveIcon data-icon="inline-start" />
                            )}
                            Save robots.txt
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </SeoSettingsShell>
    );
}
