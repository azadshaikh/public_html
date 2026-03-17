import { router } from '@inertiajs/react';
import {
    ExternalLinkIcon,
    RefreshCwIcon,
    SaveIcon,
    ScanSearchIcon,
} from 'lucide-react';
import { useState } from 'react';
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
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import {
    getSeoSettingsBreadcrumbs,
    getSeoSettingsNav,
} from '../../../lib/seo-settings';
import type { SitemapFormValues, SitemapPageProps } from '../../../types/seo';

const labels: Record<
    keyof Omit<
        SitemapFormValues,
        'enabled' | 'links_per_file' | 'auto_regenerate'
    >,
    string
> = {
    posts_enabled: 'Posts',
    pages_enabled: 'Pages',
    categories_enabled: 'Categories',
    tags_enabled: 'Tags',
    authors_enabled: 'Authors',
};

export default function SeoSitemapPage({
    initialValues,
    sitemapStatus,
}: SitemapPageProps) {
    const form = useAppForm<SitemapFormValues>({
        defaults: initialValues,
        rememberKey: 'seo.settings.sitemap',
        dirtyGuard: { enabled: true },
    });
    const [regenerating, setRegenerating] = useState(false);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('seo.settings.sitemap.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Sitemap settings updated',
                description: 'XML sitemap generation preferences were saved.',
            },
        });
    };

    const handleRegenerate = () => {
        setRegenerating(true);

        router.post(
            route('seo.sitemap.regenerate'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRegenerating(false),
            },
        );
    };

    return (
        <SettingsLayout
            settingsNav={getSeoSettingsNav()}
            breadcrumbs={getSeoSettingsBreadcrumbs('Sitemap')}
            title="Sitemap"
            description="Control which content types are published to XML sitemaps and how often search engines can rediscover them."
            activeSlug="sitemap"
            railLabel="SEO settings"
        >
            <form
                className="flex flex-col gap-6"
                onSubmit={handleSubmit}
                noValidate
            >
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <CardTitle>
                                            XML sitemap visibility
                                        </CardTitle>
                                        <CardDescription>
                                            Enable sitemap generation and choose
                                            which public content types should be
                                            included.
                                        </CardDescription>
                                    </div>
                                    <Switch
                                        checked={form.data.enabled}
                                        onCheckedChange={(checked) =>
                                            form.setField('enabled', checked)
                                        }
                                    />
                                </div>
                            </CardHeader>
                            {form.data.enabled ? (
                                <CardContent className="flex flex-col gap-6">
                                    {(
                                        Object.keys(labels) as Array<
                                            keyof Omit<
                                                SitemapFormValues,
                                                | 'enabled'
                                                | 'links_per_file'
                                                | 'auto_regenerate'
                                            >
                                        >
                                    ).map((key) => (
                                        <Field
                                            key={key}
                                            orientation="horizontal"
                                        >
                                            <Switch
                                                checked={form.data[key]}
                                                onCheckedChange={(checked) =>
                                                    form.setField(key, checked)
                                                }
                                            />
                                            <div className="flex flex-col gap-1">
                                                <FieldLabel>
                                                    {labels[key]}
                                                </FieldLabel>
                                                <FieldDescription>
                                                    Include{' '}
                                                    {labels[key].toLowerCase()}{' '}
                                                    URLs in generated sitemaps.
                                                </FieldDescription>
                                            </div>
                                        </Field>
                                    ))}

                                    <Field orientation="horizontal">
                                        <Switch
                                            checked={form.data.auto_regenerate}
                                            onCheckedChange={(checked) =>
                                                form.setField(
                                                    'auto_regenerate',
                                                    checked,
                                                )
                                            }
                                        />
                                        <div className="flex flex-col gap-1">
                                            <FieldLabel>
                                                Auto-regenerate
                                            </FieldLabel>
                                            <FieldDescription>
                                                Rebuild sitemaps automatically
                                                when content changes.
                                            </FieldDescription>
                                        </div>
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="links_per_file">
                                            Links per file
                                        </FieldLabel>
                                        <Input
                                            id="links_per_file"
                                            type="number"
                                            min={100}
                                            max={50000}
                                            value={form.data.links_per_file}
                                            onChange={(event) =>
                                                form.setField(
                                                    'links_per_file',
                                                    Number(
                                                        event.target.value || 0,
                                                    ),
                                                )
                                            }
                                        />
                                        <FieldDescription>
                                            Keep files compact for faster
                                            regeneration. The safe range is
                                            100–50,000.
                                        </FieldDescription>
                                    </Field>
                                </CardContent>
                            ) : null}
                            <CardFooter className="justify-end">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <Spinner className="mr-2 size-4" />
                                    ) : (
                                        <SaveIcon data-icon="inline-start" />
                                    )}
                                    Save sitemap settings
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <ScanSearchIcon className="size-4 text-muted-foreground" />
                                    <CardTitle>Current status</CardTitle>
                                </div>
                                <CardDescription>
                                    Review generation history and URL counts
                                    before you publish changes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4 text-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Enabled
                                    </span>
                                    <span className="font-medium">
                                        {sitemapStatus.enabled ? 'Yes' : 'No'}
                                    </span>
                                </div>
                                <div className="flex items-start justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Last generated
                                    </span>
                                    <span className="text-right font-medium">
                                        {sitemapStatus.last_generated_at ??
                                            'Never'}
                                    </span>
                                </div>
                                <div className="flex items-start justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Total URLs
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {sitemapStatus.total_urls}
                                    </span>
                                </div>
                            </CardContent>
                            <CardFooter className="flex flex-col gap-3">
                                <Button
                                    type="button"
                                    className="w-full"
                                    variant="outline"
                                    onClick={handleRegenerate}
                                    disabled={regenerating}
                                >
                                    {regenerating ? (
                                        <Spinner className="mr-2 size-4" />
                                    ) : (
                                        <RefreshCwIcon data-icon="inline-start" />
                                    )}
                                    Regenerate now
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full"
                                    asChild
                                >
                                    <a
                                        href={route('sitemap')}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLinkIcon data-icon="inline-start" />
                                        View sitemap
                                    </a>
                                </Button>
                            </CardFooter>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Type breakdown</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3 text-sm">
                                {Object.entries(sitemapStatus.types).map(
                                    ([key, item]) => (
                                        <div
                                            key={key}
                                            className="flex items-center justify-between rounded-lg border px-3 py-2"
                                        >
                                            <span>{item.label}</span>
                                            <span className="font-medium tabular-nums">
                                                {item.enabled
                                                    ? `${item.count} URLs`
                                                    : 'Disabled'}
                                            </span>
                                        </div>
                                    ),
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </SettingsLayout>
    );
}
