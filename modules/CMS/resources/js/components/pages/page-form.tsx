'use client';

import { router } from '@inertiajs/react';
import { Settings2Icon, ShieldIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CmsOption,
    MediaPickerPageProps,
    PageEditDetail,
    PageFormValues,
} from '../../types/cms';
import { CmsCollapsibleSidebarCard } from '../shared/cms-collapsible-sidebar-card';
import { CmsContentTabBody } from '../shared/cms-content-tab-body';
import { CmsDangerZoneCard } from '../shared/cms-danger-zone-card';
import { CmsFeaturedImageCard } from '../shared/cms-featured-image-card';
import { RequiredLabel, buildPermalink, slugify } from '../shared/cms-form-utils';
import { CmsRevisionsSection } from '../shared/cms-revisions-section';
import { CmsSeoFields } from '../shared/cms-seo-fields';
import { CmsSlugField } from '../shared/cms-slug-field';
import { CmsSocialFields } from '../shared/cms-social-fields';
import { CmsStickyFormFooter } from '../shared/cms-sticky-form-footer';
import { CmsTabSections } from '../shared/cms-tab-sections';

type PageFormProps = {
    mode: 'create' | 'edit';
    initialValues?: PageFormValues;
    parentPageOptions: CmsOption[];
    authorOptions: CmsOption[];
    metaRobotsOptions: CmsOption[];
    statusOptions: CmsOption[];
    visibilityOptions: CmsOption[];
    templateOptions: CmsOption[];
    preSlug: string;
    baseUrl: string;
    page?: PageEditDetail;
} & MediaPickerPageProps;

const emptyValues: PageFormValues = {
    title: '',
    slug: '',
    content: '',
    excerpt: '',
    feature_image: '',
    status: 'draft',
    visibility: 'public',
    post_password: '',
    password_hint: '',
    author_id: '',
    published_at: '',
    parent_id: '',
    template: '',
    meta_title: '',
    meta_description: '',
    meta_robots: '',
    og_title: '',
    og_description: '',
    og_image: '',
    og_url: '',
    schema: '',
};

export default function PageForm({
    mode,
    initialValues,
    parentPageOptions,
    authorOptions,
    metaRobotsOptions,
    statusOptions,
    visibilityOptions,
    templateOptions,
    preSlug,
    baseUrl,
    page,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
}: PageFormProps) {
    const form = useAppForm<PageFormValues>({
        defaults: initialValues || emptyValues,
        rememberKey:
            mode === 'create'
                ? 'cms.pages.create.form'
                : `cms.pages.edit.${page?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            title: [formValidators.required('Title')],
            status: [formValidators.required('Status')],
            author_id: [formValidators.required('Author')],
        },
    });
    const { data, setField } = form;

    const [slugTouched, setSlugTouched] = useState(mode === 'edit');

    useEffect(() => {
        if (mode !== 'create' || slugTouched) {
            return;
        }

        const nextSlug = slugify(data.title);

        if (data.slug === nextSlug) {
            return;
        }

        setField('slug', nextSlug);
    }, [data.slug, data.title, mode, setField, slugTouched]);

    const permalinkPreview = useMemo(
        () => page?.permalink_url ?? buildPermalink(baseUrl, preSlug, form.data.slug),
        [baseUrl, form.data.slug, page?.permalink_url, preSlug],
    );

    const showPublishAt =
        form.data.status === 'published' || form.data.status === 'scheduled';
    const showPasswordFields = form.data.visibility === 'password';
    const showParentPageField = parentPageOptions.length > 1;
    const showTemplateField = templateOptions.length > 1;
    const hasMoreOptionsErrors = Boolean(
        form.invalid('slug') ||
            form.invalid('author_id') ||
            form.invalid('template'),
    );

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('cms.pages.store')
            : route('cms.pages.update', page!.id);

    const submitLabel = mode === 'create' ? 'Create Page' : 'Save Changes';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title: mode === 'create' ? 'Page created' : 'Page updated',
                description:
                    mode === 'create'
                        ? 'The page has been created successfully.'
                        : 'The page has been updated successfully.',
            },
        });
    };

    const handleDelete = () => {
        if (!page) {
            return;
        }

        if (!window.confirm(`Move "${page.title}" to trash?`)) {
            return;
        }

        router.delete(route('cms.pages.destroy', page.id), {
            preserveScroll: true,
        });
    };

    return (
        <form
            className="flex flex-col gap-6 pb-20"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex flex-col gap-6">
                    <Field
                        data-invalid={form.invalid('title') || undefined}
                    >
                        <RequiredLabel htmlFor="title">Title</RequiredLabel>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setField('title', event.target.value)
                            }
                            onBlur={() => form.touch('title')}
                            aria-invalid={form.invalid('title') || undefined}
                            placeholder="Enter page title"
                        />
                        <FieldError>{form.error('title')}</FieldError>
                    </Field>

                    <CmsTabSections
                        defaultValue="content"
                        tabs={[
                            {
                                value: 'content',
                                label: 'Content',
                                contentClassName: 'flex flex-col gap-6',
                                content: (
                                    <CmsContentTabBody
                                        contentValue={form.data.content}
                                        excerptValue={form.data.excerpt}
                                        onContentChange={(value) =>
                                            form.setField('content', value)
                                        }
                                        onExcerptChange={(value) =>
                                            form.setField('excerpt', value)
                                        }
                                        onContentBlur={() =>
                                            form.touch('content')
                                        }
                                        onExcerptBlur={() =>
                                            form.touch('excerpt')
                                        }
                                        contentInvalid={form.invalid(
                                            'content',
                                        )}
                                        excerptInvalid={form.invalid(
                                            'excerpt',
                                        )}
                                        contentError={form.error('content')}
                                        excerptError={form.error('excerpt')}
                                        contentPlaceholder="Write the full page content"
                                        updatedAtHuman={
                                            page?.updated_at_human
                                        }
                                        updatedAtFormatted={
                                            page?.updated_at_formatted
                                        }
                                    />
                                ),
                            },
                            {
                                value: 'seo',
                                label: 'SEO',
                                contentClassName: 'flex flex-col gap-6',
                                content: (
                                    <CmsSeoFields
                                        metaTitle={form.data.meta_title}
                                        metaDescription={
                                            form.data.meta_description
                                        }
                                        metaRobots={form.data.meta_robots}
                                        metaRobotsOptions={metaRobotsOptions}
                                        onMetaTitleChange={(value) =>
                                            form.setField('meta_title', value)
                                        }
                                        onMetaDescriptionChange={(value) =>
                                            form.setField(
                                                'meta_description',
                                                value,
                                            )
                                        }
                                        onMetaRobotsChange={(value) =>
                                            form.setField('meta_robots', value)
                                        }
                                        onMetaTitleBlur={() =>
                                            form.touch('meta_title')
                                        }
                                        onMetaDescriptionBlur={() =>
                                            form.touch('meta_description')
                                        }
                                        onMetaRobotsBlur={() =>
                                            form.touch('meta_robots')
                                        }
                                        metaTitleInvalid={form.invalid(
                                            'meta_title',
                                        )}
                                        metaDescriptionInvalid={form.invalid(
                                            'meta_description',
                                        )}
                                        metaRobotsInvalid={form.invalid(
                                            'meta_robots',
                                        )}
                                        metaTitleError={form.error(
                                            'meta_title',
                                        )}
                                        metaDescriptionError={form.error(
                                            'meta_description',
                                        )}
                                        metaRobotsError={form.error(
                                            'meta_robots',
                                        )}
                                        surfaceClassName="bg-background"
                                    />
                                ),
                            },
                            {
                                value: 'social',
                                label: 'Social',
                                contentClassName: 'flex flex-col gap-6',
                                content: (
                                    <CmsSocialFields
                                        ogTitle={form.data.og_title}
                                        ogDescription={
                                            form.data.og_description
                                        }
                                        ogImage={form.data.og_image}
                                        ogUrl={form.data.og_url}
                                        onOgTitleChange={(value) =>
                                            form.setField('og_title', value)
                                        }
                                        onOgDescriptionChange={(value) =>
                                            form.setField(
                                                'og_description',
                                                value,
                                            )
                                        }
                                        onOgImageChange={(value) =>
                                            form.setField('og_image', value)
                                        }
                                        onOgUrlChange={(value) =>
                                            form.setField('og_url', value)
                                        }
                                        onOgTitleBlur={() =>
                                            form.touch('og_title')
                                        }
                                        onOgDescriptionBlur={() =>
                                            form.touch('og_description')
                                        }
                                        onOgImageBlur={() =>
                                            form.touch('og_image')
                                        }
                                        onOgUrlBlur={() =>
                                            form.touch('og_url')
                                        }
                                        ogTitleInvalid={form.invalid(
                                            'og_title',
                                        )}
                                        ogDescriptionInvalid={form.invalid(
                                            'og_description',
                                        )}
                                        ogImageInvalid={form.invalid(
                                            'og_image',
                                        )}
                                        ogUrlInvalid={form.invalid('og_url')}
                                        ogTitleError={form.error('og_title')}
                                        ogDescriptionError={form.error(
                                            'og_description',
                                        )}
                                        ogImageError={form.error('og_image')}
                                        ogUrlError={form.error('og_url')}
                                        surfaceClassName="bg-background"
                                        ogUrlPlaceholder="https://example.com/your-page"
                                    />
                                ),
                            },
                            {
                                value: 'schema',
                                label: 'Schema',
                                contentClassName: 'flex flex-col gap-4',
                                content: (
                                    <Field
                                        data-invalid={
                                            form.invalid('schema') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="schema">
                                            Schema markup
                                        </FieldLabel>
                                        <MonacoEditor
                                            name="schema"
                                            language="html"
                                            height={360}
                                            value={form.data.schema}
                                            onChange={(value) =>
                                                form.setField('schema', value)
                                            }
                                            onBlur={() => form.touch('schema')}
                                            placeholder="Add custom schema markup"
                                        />
                                        <FieldDescription>
                                            Optional structured data for search
                                            engines.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('schema')}
                                        </FieldError>
                                    </Field>
                                ),
                            },
                        ]}
                    />
                </div>

                <div className="flex flex-col gap-4">
                    <CmsFeaturedImageCard
                        value={form.data.feature_image}
                        previewUrl={page?.featured_image_url}
                        onChange={(value) =>
                            form.setField('feature_image', value)
                        }
                        onTouch={() => form.touch('feature_image')}
                        invalid={form.invalid('feature_image')}
                        error={form.error('feature_image')}
                        pickerMedia={pickerMedia}
                        pickerFilters={pickerFilters}
                        uploadSettings={uploadSettings}
                        pickerStatistics={pickerStatistics}
                        pickerAction={
                            mode === 'create'
                                ? route('cms.pages.create')
                                : route('cms.pages.edit', page!.id)
                        }
                    />

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2Icon className="size-4 text-muted-foreground" />
                                <CardTitle>Publish settings</CardTitle>
                            </div>
                            <CardDescription>
                                Control publishing, parent page, and visibility.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <FieldGroup className="md:grid md:grid-cols-2 md:gap-4">
                                <Field
                                    data-invalid={
                                        form.invalid('status') || undefined
                                    }
                                >
                                    <RequiredLabel htmlFor="status">
                                        Status
                                    </RequiredLabel>
                                    <NativeSelect
                                        id="status"
                                        className="w-full"
                                        value={form.data.status}
                                        onChange={(event) => {
                                            const nextStatus =
                                                event.target.value;
                                            form.setField('status', nextStatus);

                                            if (
                                                nextStatus !== 'published' &&
                                                nextStatus !== 'scheduled'
                                            ) {
                                                form.setField(
                                                    'published_at',
                                                    '',
                                                );
                                            }
                                        }}
                                        onBlur={() => form.touch('status')}
                                        aria-invalid={
                                            form.invalid('status') || undefined
                                        }
                                    >
                                        {statusOptions.map((option) => (
                                            <NativeSelectOption
                                                key={String(option.value)}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('status')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('visibility') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="visibility">
                                        Visibility
                                    </FieldLabel>
                                    <NativeSelect
                                        id="visibility"
                                        className="w-full"
                                        value={form.data.visibility}
                                        onChange={(event) => {
                                            const nextVisibility =
                                                event.target.value;
                                            form.setField(
                                                'visibility',
                                                nextVisibility,
                                            );

                                            if (nextVisibility !== 'password') {
                                                form.setField(
                                                    'post_password',
                                                    '',
                                                );
                                                form.setField(
                                                    'password_hint',
                                                    '',
                                                );
                                            }
                                        }}
                                        onBlur={() => form.touch('visibility')}
                                        aria-invalid={
                                            form.invalid('visibility') ||
                                            undefined
                                        }
                                    >
                                        {visibilityOptions.map((option) => (
                                            <NativeSelectOption
                                                key={String(option.value)}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('visibility')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            {showParentPageField ? (
                                <Field
                                    data-invalid={
                                        form.invalid('parent_id') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="parent_id">
                                        Parent page
                                    </FieldLabel>
                                    <NativeSelect
                                        id="parent_id"
                                        className="w-full"
                                        value={form.data.parent_id}
                                        onChange={(event) =>
                                            form.setField(
                                                'parent_id',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('parent_id')}
                                        aria-invalid={
                                            form.invalid('parent_id') ||
                                            undefined
                                        }
                                    >
                                        {parentPageOptions.map((option) => (
                                            <NativeSelectOption
                                                key={String(option.value)}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('parent_id')}
                                    </FieldError>
                                </Field>
                            ) : null}

                            {showPublishAt ? (
                                <Field
                                    data-invalid={
                                        form.invalid('published_at') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="published_at">
                                        Publish at
                                    </FieldLabel>
                                    <Input
                                        id="published_at"
                                        type="datetime-local"
                                        value={form.data.published_at}
                                        onChange={(event) =>
                                            form.setField(
                                                'published_at',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('published_at')
                                        }
                                        aria-invalid={
                                            form.invalid('published_at') ||
                                            undefined
                                        }
                                    />
                                    <FieldDescription>
                                        {form.data.status === 'scheduled'
                                            ? 'Choose a future date and time for scheduling.'
                                            : 'Choose the publish date and time.'}
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('published_at')}
                                    </FieldError>
                                </Field>
                            ) : null}

                            {showPasswordFields ? (
                                <>
                                    <Field
                                        data-invalid={
                                            form.invalid('post_password') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="post_password">
                                            Password
                                        </FieldLabel>
                                        <Input
                                            id="post_password"
                                            type="password"
                                            value={form.data.post_password}
                                            onChange={(event) =>
                                                form.setField(
                                                    'post_password',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('post_password')
                                            }
                                            aria-invalid={
                                                form.invalid('post_password') ||
                                                undefined
                                            }
                                            placeholder={
                                                page?.is_password_protected
                                                    ? 'Leave blank to keep the current password'
                                                    : 'Enter password'
                                            }
                                        />
                                        <FieldDescription>
                                            {page?.is_password_protected
                                                ? 'Leave blank to keep the current password, or enter a new one to change it.'
                                                : 'Visitors must enter this password to view the content.'}
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('post_password')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('password_hint') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="password_hint">
                                            Password hint
                                        </FieldLabel>
                                        <Input
                                            id="password_hint"
                                            value={form.data.password_hint}
                                            onChange={(event) =>
                                                form.setField(
                                                    'password_hint',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('password_hint')
                                            }
                                            aria-invalid={
                                                form.invalid('password_hint') ||
                                                undefined
                                            }
                                            placeholder="Optional hint for visitors"
                                        />
                                        <FieldError>
                                            {form.error('password_hint')}
                                        </FieldError>
                                    </Field>
                                </>
                            ) : null}
                        </CardContent>
                    </Card>

                    <CmsCollapsibleSidebarCard
                        title="More options"
                        description="Fine-tune permalink, author, and template."
                        icon={ShieldIcon}
                        hasErrors={hasMoreOptionsErrors}
                    >
                            <CmsSlugField
                                value={form.data.slug}
                                preSlug={preSlug}
                                permalinkPreview={permalinkPreview}
                                hasPermalink={Boolean(page?.permalink_url)}
                                onChange={(raw) => {
                                    setSlugTouched(true);
                                    form.setField('slug', slugify(raw));
                                }}
                                onTouch={() => form.touch('slug')}
                                invalid={form.invalid('slug')}
                                error={form.error('slug')}
                            />

                            <Field
                                data-invalid={
                                    form.invalid('author_id') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="author_id">
                                    Author
                                </RequiredLabel>
                                <NativeSelect
                                    id="author_id"
                                    className="w-full"
                                    value={String(form.data.author_id)}
                                    onChange={(event) =>
                                        form.setField(
                                            'author_id',
                                            event.target.value === ''
                                                ? ''
                                                : Number.parseInt(
                                                      event.target.value,
                                                      10,
                                                  ),
                                        )
                                    }
                                    onBlur={() => form.touch('author_id')}
                                    aria-invalid={
                                        form.invalid('author_id') || undefined
                                    }
                                >
                                    <NativeSelectOption value="">
                                        Select author
                                    </NativeSelectOption>
                                    {authorOptions.map((option) => (
                                        <NativeSelectOption
                                            key={String(option.value)}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>
                                    {form.error('author_id')}
                                </FieldError>
                            </Field>

                            {showTemplateField ? (
                                <Field
                                    data-invalid={
                                        form.invalid('template') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="template">
                                        Template
                                    </FieldLabel>
                                    <NativeSelect
                                        id="template"
                                        className="w-full"
                                        value={form.data.template}
                                        onChange={(event) =>
                                            form.setField(
                                                'template',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('template')}
                                        aria-invalid={
                                            form.invalid('template') ||
                                            undefined
                                        }
                                    >
                                        {templateOptions.map((option) => (
                                            <NativeSelectOption
                                                key={String(option.value)}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldDescription>
                                        Choose a different presentation template
                                        for this page.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('template')}
                                    </FieldError>
                                </Field>
                            ) : null}

                            {page ? (
                                <CmsRevisionsSection
                                    revisionsCount={page.revisions_count}
                                    revisions={page.revisions}
                                />
                            ) : null}
                    </CmsCollapsibleSidebarCard>

                    <CmsDangerZoneCard
                        show={mode === 'edit' && Boolean(page)}
                        description="Move this page to trash. You can restore it later from the trash tab."
                        onDelete={handleDelete}
                    />
                </div>
            </div>

            <CmsStickyFormFooter
                backHref={route('cms.pages.index')}
                backLabel="Back to Pages"
                submitLabel={submitLabel}
                isCreate={mode === 'create'}
                isDirty={form.isDirty}
                isProcessing={form.processing}
            />
        </form>
    );
}
