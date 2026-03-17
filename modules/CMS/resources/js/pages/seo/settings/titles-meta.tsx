import {
    AlertTriangleIcon,
    FileSearchIcon,
    Layers3Icon,
    SaveIcon,
    SearchIcon,
    Settings2Icon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import { formValidators } from '@/lib/forms';
import {
    getSeoSettingsBreadcrumbs,
    getSeoSettingsNav,
} from '../../../lib/seo-settings';
import type {
    TitlesMetaGeneralValues,
    TitlesMetaPageProps,
    TitlesMetaSectionConfig,
    TitlesMetaSectionKey,
    TitlesMetaTemplateValues,
} from '../../../types/seo';

const sectionLabels: Record<TitlesMetaSectionKey, string> = {
    general: 'General',
    posts: 'Posts',
    pages: 'Pages',
    categories: 'Categories',
    tags: 'Tags',
    authors: 'Authors',
    search: 'Search',
    error_page: 'Error Pages',
};

function GeneralSettingsForm({
    initialValues,
    urlExtensionOptions,
}: {
    initialValues: TitlesMetaGeneralValues;
    urlExtensionOptions: TitlesMetaPageProps['urlExtensionOptions'];
}) {
    const form = useAppForm<TitlesMetaGeneralValues>({
        defaults: initialValues,
        rememberKey: 'seo.settings.titlesmeta.general',
        dirtyGuard: { enabled: true },
        rules: {
            separator_character: [formValidators.required('Title separator')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('seo.settings.general.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'General SEO settings updated',
                description:
                    'The title separator, URL, and indexing settings were saved.',
            },
        });
    };

    return (
        <form
            className="flex flex-col gap-6"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            {!form.data.search_engine_visibility ? (
                <Alert variant="destructive">
                    <AlertTriangleIcon className="size-4" />
                    <AlertTitle>Search engines are blocked</AlertTitle>
                    <AlertDescription>
                        The site is currently set to $noindex, nofollow$. Switch
                        visibility on when you are ready for indexing.
                    </AlertDescription>
                </Alert>
            ) : null}

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Settings2Icon className="size-4 text-muted-foreground" />
                        <CardTitle>Global SEO defaults</CardTitle>
                    </div>
                    <CardDescription>
                        Define shared title separators, CMS URL structure, and
                        overall crawl visibility.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                    <Field
                        data-invalid={
                            form.invalid('separator_character') || undefined
                        }
                    >
                        <FieldLabel htmlFor="separator_character">
                            Title separator
                        </FieldLabel>
                        <Input
                            id="separator_character"
                            value={form.data.separator_character}
                            onChange={(event) =>
                                form.setField(
                                    'separator_character',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('separator_character')}
                            aria-invalid={
                                form.invalid('separator_character') || undefined
                            }
                            placeholder="|"
                        />
                        <FieldDescription>
                            Used between pieces of your page title, for example
                            “Page Title | Site Name”.
                        </FieldDescription>
                        <FieldError>
                            {form.error('separator_character')}
                        </FieldError>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="secondary_separator_character">
                            Secondary separator
                        </FieldLabel>
                        <Input
                            id="secondary_separator_character"
                            value={form.data.secondary_separator_character}
                            onChange={(event) =>
                                form.setField(
                                    'secondary_separator_character',
                                    event.target.value,
                                )
                            }
                            onBlur={() =>
                                form.touch('secondary_separator_character')
                            }
                            placeholder="·"
                        />
                        <FieldDescription>
                            Optional fallback for breadcrumbs or secondary title
                            formatting.
                        </FieldDescription>
                        <FieldError>
                            {form.error('secondary_separator_character')}
                        </FieldError>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="cms_base">
                            CMS URL prefix
                        </FieldLabel>
                        <Input
                            id="cms_base"
                            value={form.data.cms_base}
                            onChange={(event) =>
                                form.setField('cms_base', event.target.value)
                            }
                            onBlur={() => form.touch('cms_base')}
                            placeholder="blog"
                        />
                        <FieldDescription>
                            Leave blank for root-level URLs or set a shared
                            prefix like “blog” or “news”.
                        </FieldDescription>
                        <FieldError>{form.error('cms_base')}</FieldError>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="url_extension">
                            URL extension
                        </FieldLabel>
                        <NativeSelect
                            id="url_extension"
                            className="w-full"
                            value={form.data.url_extension}
                            onChange={(event) =>
                                form.setField(
                                    'url_extension',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('url_extension')}
                        >
                            {urlExtensionOptions.map((option) => (
                                <NativeSelectOption
                                    key={String(option.value)}
                                    value={String(option.value)}
                                >
                                    {option.label}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                        <FieldDescription>
                            Most modern sites keep clean URLs with no extension.
                        </FieldDescription>
                        <FieldError>{form.error('url_extension')}</FieldError>
                    </Field>

                    <Field orientation="horizontal">
                        <Switch
                            checked={form.data.search_engine_visibility}
                            onCheckedChange={(checked) =>
                                form.setField(
                                    'search_engine_visibility',
                                    checked,
                                )
                            }
                        />
                        <div className="flex flex-col gap-1">
                            <FieldLabel>Search engine visibility</FieldLabel>
                            <FieldDescription>
                                Disable this during development or private
                                launches. Enable it for production indexing.
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
                        Save general settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function TemplateSectionForm({
    section,
    metaRobotsOptions,
}: {
    section: TitlesMetaSectionConfig;
    metaRobotsOptions: TitlesMetaPageProps['metaRobotsOptions'];
}) {
    const form = useAppForm<TitlesMetaTemplateValues>({
        defaults: section.initialValues,
        rememberKey: `seo.settings.titlesmeta.${section.key}`,
        dirtyGuard: { enabled: true },
        rules: {
            title_template: [formValidators.required('Title template')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(
            'post',
            route('seo.settings.update', {
                master_group: 'cms',
                file_name: section.key,
            }),
            {
                preserveScroll: true,
                setDefaultsOnSuccess: true,
                successToast: {
                    title: `${section.title} settings updated`,
                    description: `The ${section.title.toLowerCase()} SEO rules were saved successfully.`,
                },
            },
        );
    };

    return (
        <form
            className="flex flex-col gap-6"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Layers3Icon className="size-4 text-muted-foreground" />
                        <CardTitle>{section.title} metadata</CardTitle>
                    </div>
                    <CardDescription>{section.description}</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                    {section.helperText ? (
                        <Alert>
                            <FileSearchIcon className="size-4" />
                            <AlertTitle>Template tips</AlertTitle>
                            <AlertDescription>
                                {section.helperText}
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {section.supportsPermalinkBase ? (
                        <Field>
                            <FieldLabel
                                htmlFor={`${section.key}-permalink_base`}
                            >
                                URL prefix
                            </FieldLabel>
                            <Input
                                id={`${section.key}-permalink_base`}
                                value={form.data.permalink_base}
                                onChange={(event) =>
                                    form.setField(
                                        'permalink_base',
                                        event.target.value,
                                    )
                                }
                                onBlur={() => form.touch('permalink_base')}
                                placeholder="category"
                            />
                            <FieldDescription>
                                {section.previewPattern ??
                                    'Set a clean archive prefix or leave it empty for the default structure.'}
                            </FieldDescription>
                            <FieldError>
                                {form.error('permalink_base')}
                            </FieldError>
                        </Field>
                    ) : null}

                    <Field
                        data-invalid={
                            form.invalid('title_template') || undefined
                        }
                    >
                        <FieldLabel htmlFor={`${section.key}-title_template`}>
                            Title template
                        </FieldLabel>
                        <Input
                            id={`${section.key}-title_template`}
                            value={form.data.title_template}
                            onChange={(event) =>
                                form.setField(
                                    'title_template',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('title_template')}
                            aria-invalid={
                                form.invalid('title_template') || undefined
                            }
                            placeholder="%title% %separator% %site_title%"
                        />
                        <FieldDescription>
                            Use placeholders like %title%, %site_title%,
                            %separator%, and section-specific values.
                        </FieldDescription>
                        <FieldError>{form.error('title_template')}</FieldError>
                    </Field>

                    <Field>
                        <FieldLabel
                            htmlFor={`${section.key}-description_template`}
                        >
                            Meta description template
                        </FieldLabel>
                        <Textarea
                            id={`${section.key}-description_template`}
                            rows={4}
                            value={form.data.description_template}
                            onChange={(event) =>
                                form.setField(
                                    'description_template',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('description_template')}
                            placeholder="Use a concise template for search snippets"
                        />
                        <FieldDescription>
                            Keep descriptions under roughly 160 characters for
                            best snippet rendering.
                        </FieldDescription>
                        <FieldError>
                            {form.error('description_template')}
                        </FieldError>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor={`${section.key}-robots_default`}>
                            Default robots directive
                        </FieldLabel>
                        <NativeSelect
                            id={`${section.key}-robots_default`}
                            className="w-full"
                            value={form.data.robots_default}
                            onChange={(event) =>
                                form.setField(
                                    'robots_default',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.touch('robots_default')}
                        >
                            <NativeSelectOption value="">
                                Select a robots directive
                            </NativeSelectOption>
                            {metaRobotsOptions.map((option) => (
                                <NativeSelectOption
                                    key={String(option.value)}
                                    value={String(option.value)}
                                >
                                    {option.label}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                        <FieldDescription>
                            Choose the crawl default when content does not
                            define its own robots rule.
                        </FieldDescription>
                        <FieldError>{form.error('robots_default')}</FieldError>
                    </Field>

                    {section.supportsPermalinkStructure ? (
                        <Field>
                            <FieldLabel>Post permalink structure</FieldLabel>
                            <div className="grid gap-3">
                                {[
                                    {
                                        value: '%year%/%monthnum%/%day%/%postname%',
                                        label: '/2026/03/16/sample-post',
                                    },
                                    {
                                        value: '%year%/%monthnum%/%postname%',
                                        label: '/2026/03/sample-post',
                                    },
                                    {
                                        value: '%post_id%',
                                        label: '/123',
                                    },
                                    {
                                        value: '%postname%',
                                        label: '/sample-post',
                                    },
                                    {
                                        value: '%category%/%postname%',
                                        label: '/category/sample-post',
                                    },
                                ].map((option) => {
                                    const checked =
                                        form.data.permalink_structure ===
                                        option.value;

                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            className={`flex items-start justify-between rounded-xl border px-4 py-3 text-left transition ${
                                                checked
                                                    ? 'border-primary bg-primary/5'
                                                    : 'hover:border-primary/50 hover:bg-muted/40'
                                            }`}
                                            onClick={() =>
                                                form.setField(
                                                    'permalink_structure',
                                                    option.value,
                                                )
                                            }
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {option.value}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {option.label}
                                                </div>
                                            </div>
                                            {checked ? (
                                                <Badge>Selected</Badge>
                                            ) : null}
                                        </button>
                                    );
                                })}
                            </div>
                            <FieldDescription>
                                Simpler structures are usually easier to read
                                and share.
                            </FieldDescription>
                        </Field>
                    ) : null}

                    {section.supportsMultipleCategories ? (
                        <Field orientation="horizontal">
                            <Switch
                                checked={form.data.enable_multiple_categories}
                                onCheckedChange={(checked) =>
                                    form.setField(
                                        'enable_multiple_categories',
                                        checked,
                                    )
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <FieldLabel>
                                    Allow multiple categories per post
                                </FieldLabel>
                                <FieldDescription>
                                    Enable this if editorial workflows rely on
                                    more than one category assignment.
                                </FieldDescription>
                            </div>
                        </Field>
                    ) : null}

                    {section.supportsPaginationIndexing ? (
                        <Field orientation="horizontal">
                            <Switch
                                checked={form.data.enable_pagination_indexing}
                                onCheckedChange={(checked) =>
                                    form.setField(
                                        'enable_pagination_indexing',
                                        checked,
                                    )
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <FieldLabel>Index paginated pages</FieldLabel>
                                <FieldDescription>
                                    Usually disabled to avoid duplicate archive
                                    pages competing in search.
                                </FieldDescription>
                            </div>
                        </Field>
                    ) : null}
                </CardContent>
                <CardFooter className="justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner className="mr-2 size-4" />
                        ) : (
                            <SaveIcon data-icon="inline-start" />
                        )}
                        Save {section.title.toLowerCase()} settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

export default function SeoTitlesMetaPage({
    activeSection,
    metaRobotsOptions,
    urlExtensionOptions,
    generalInitialValues,
    sections,
}: TitlesMetaPageProps) {
    const [currentSection, setCurrentSection] =
        useState<TitlesMetaSectionKey>(activeSection);

    useEffect(() => {
        setCurrentSection(activeSection);
    }, [activeSection]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('section', currentSection);
        window.history.replaceState({}, '', url.toString());
    }, [currentSection]);

    const sectionMap = useMemo(
        () =>
            Object.fromEntries(
                sections.map((section) => [section.key, section]),
            ),
        [sections],
    );

    const activeLabel = sectionLabels[currentSection];

    return (
        <SettingsLayout
            settingsNav={getSeoSettingsNav()}
            breadcrumbs={getSeoSettingsBreadcrumbs('Titles & Meta')}
            title="Titles & Meta"
            description="Manage global title formats, archive defaults, and crawl directives for every CMS surface."
            activeSlug="titlesmeta"
            railLabel="SEO settings"
        >
            <div className="flex flex-col gap-6">
                <Alert>
                    <SearchIcon className="size-4" />
                    <AlertTitle>{activeLabel} templates</AlertTitle>
                    <AlertDescription>
                        Keep templates readable, consistent, and focused on
                        primary keywords. Content-specific overrides still win
                        when provided.
                    </AlertDescription>
                </Alert>

                <Tabs
                    value={currentSection}
                    onValueChange={(value) =>
                        setCurrentSection(value as TitlesMetaSectionKey)
                    }
                >
                    <TabsList
                        variant="line"
                        className="flex h-auto flex-wrap gap-2 rounded-xl border bg-background p-2"
                    >
                        {Object.entries(sectionLabels).map(([key, label]) => (
                            <TabsTrigger
                                key={key}
                                value={key}
                                className="rounded-lg px-3 py-2"
                            >
                                {label}
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="general" className="mt-6">
                        <GeneralSettingsForm
                            initialValues={generalInitialValues}
                            urlExtensionOptions={urlExtensionOptions}
                        />
                    </TabsContent>

                    {sections.map((section) => (
                        <TabsContent
                            key={section.key}
                            value={section.key}
                            className="mt-6"
                        >
                            <TemplateSectionForm
                                section={sectionMap[section.key]}
                                metaRobotsOptions={metaRobotsOptions}
                            />
                        </TabsContent>
                    ))}
                </Tabs>
            </div>
        </SettingsLayout>
    );
}
