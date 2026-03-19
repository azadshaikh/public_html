'use client';

import { router } from '@inertiajs/react';
import { Settings2Icon, ShieldIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
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
    CategoryEditDetail,
    CategoryFormValues,
    CmsOption,
    MediaPickerPageProps,
} from '../../types/cms';
import { CmsCollapsibleSidebarCard } from '../shared/cms-collapsible-sidebar-card';
import { CmsContentTabBody } from '../shared/cms-content-tab-body';
import { CmsDangerZoneCard } from '../shared/cms-danger-zone-card';
import { CmsFeaturedImageCard } from '../shared/cms-featured-image-card';
import { RequiredLabel, buildPermalink, slugify } from '../shared/cms-form-utils';
import { CmsRevisionsSection } from '../shared/cms-revisions-section';
import { CmsSchemaTextareaField } from '../shared/cms-schema-textarea-field';
import { CmsSeoFields } from '../shared/cms-seo-fields';
import { CmsSlugField } from '../shared/cms-slug-field';
import { CmsSocialFields } from '../shared/cms-social-fields';
import { CmsStickyFormFooter } from '../shared/cms-sticky-form-footer';
import { CmsTabSections } from '../shared/cms-tab-sections';

type CategoryFormProps = {
    mode: 'create' | 'edit';
    initialValues?: CategoryFormValues;
    parentCategoryOptions: CmsOption[];
    statusOptions: CmsOption[];
    metaRobotsOptions: CmsOption[];
    templateOptions: CmsOption[];
    preSlug: string;
    baseUrl: string;
    category?: CategoryEditDetail;
} & MediaPickerPageProps;

const emptyValues: CategoryFormValues = {
    title: '',
    slug: '',
    content: '',
    excerpt: '',
    feature_image: '',
    status: 'draft',
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

export default function CategoryForm({
    mode,
    initialValues,
    parentCategoryOptions,
    statusOptions,
    metaRobotsOptions,
    templateOptions,
    preSlug,
    baseUrl,
    category,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
}: CategoryFormProps) {
    const form = useAppForm<CategoryFormValues>({
        defaults: initialValues || emptyValues,
        rememberKey:
            mode === 'create'
                ? 'cms.categories.create.form'
                : `cms.categories.edit.${category?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            title: [formValidators.required('Title')],
            status: [formValidators.required('Status')],
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
        () => category?.permalink_url ?? buildPermalink(baseUrl, preSlug, form.data.slug),
        [baseUrl, form.data.slug, category?.permalink_url, preSlug],
    );

    const showTemplateField = templateOptions.length > 1;
    const hasMoreOptionsErrors = Boolean(
        form.invalid('slug') || form.invalid('template'),
    );

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('cms.categories.store')
            : route('cms.categories.update', category!.id);

    const submitLabel = mode === 'create' ? 'Create Category' : 'Save Changes';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title:
                    mode === 'create' ? 'Category created' : 'Category updated',
                description:
                    mode === 'create'
                        ? 'The category has been created successfully.'
                        : 'The category has been updated successfully.',
            },
        });
    };

    const handleDelete = () => {
        if (!category) {
            return;
        }

        if (!window.confirm(`Move "${category.title}" to trash?`)) {
            return;
        }

        router.delete(route('cms.categories.destroy', category.id), {
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
                    <Field data-invalid={form.invalid('title') || undefined}>
                        <RequiredLabel htmlFor="title">Title</RequiredLabel>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setField('title', event.target.value)
                            }
                            onBlur={() => form.touch('title')}
                            aria-invalid={form.invalid('title') || undefined}
                            placeholder="Enter category title"
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
                                        contentLabel="Description"
                                        contentPlaceholder="Write the category description"
                                        updatedAtHuman={
                                            category?.updated_at_human
                                        }
                                        updatedAtFormatted={
                                            category?.updated_at_formatted
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
                                        ogUrlPlaceholder="https://example.com/your-category"
                                    />
                                ),
                            },
                            {
                                value: 'schema',
                                label: 'Schema',
                                contentClassName: 'flex flex-col gap-4',
                                content: (
                                    <CmsSchemaTextareaField
                                        value={form.data.schema}
                                        onChange={(value) =>
                                            form.setField('schema', value)
                                        }
                                        onBlur={() => form.touch('schema')}
                                        invalid={form.invalid('schema')}
                                        error={form.error('schema')}
                                        className="bg-background font-mono text-sm"
                                    />
                                ),
                            },
                        ]}
                    />
                </div>

                <div className="flex flex-col gap-4">
                    <CmsFeaturedImageCard
                        value={form.data.feature_image}
                        previewUrl={category?.featured_image_url}
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
                                ? route('cms.categories.create')
                                : route('cms.categories.edit', category!.id)
                        }
                    />

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2Icon className="size-4 text-muted-foreground" />
                                <CardTitle>Settings</CardTitle>
                            </div>
                            <CardDescription>
                                Control publishing and category hierarchy.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <FieldGroup>
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
                                        onChange={(event) =>
                                            form.setField(
                                                'status',
                                                event.target.value,
                                            )
                                        }
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

                                {parentCategoryOptions.length > 1 ? (
                                    <Field
                                        data-invalid={
                                            form.invalid('parent_id') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="parent_id">
                                            Parent category
                                        </FieldLabel>
                                        <NativeSelect
                                            id="parent_id"
                                            className="w-full"
                                            value={String(form.data.parent_id)}
                                            onChange={(event) =>
                                                form.setField(
                                                    'parent_id',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('parent_id')
                                            }
                                            aria-invalid={
                                                form.invalid('parent_id') ||
                                                undefined
                                            }
                                        >
                                            {parentCategoryOptions.map(
                                                (option) => (
                                                    <NativeSelectOption
                                                        key={String(
                                                            option.value,
                                                        )}
                                                        value={String(
                                                            option.value,
                                                        )}
                                                    >
                                                        {option.label}
                                                    </NativeSelectOption>
                                                ),
                                            )}
                                        </NativeSelect>
                                        <FieldError>
                                            {form.error('parent_id')}
                                        </FieldError>
                                    </Field>
                                ) : null}
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <CmsCollapsibleSidebarCard
                        title="More options"
                        description="Fine-tune the permalink and template."
                        icon={ShieldIcon}
                        hasErrors={hasMoreOptionsErrors}
                    >
                            <CmsSlugField
                                value={form.data.slug}
                                preSlug={preSlug}
                                permalinkPreview={permalinkPreview}
                                hasPermalink={Boolean(category?.permalink_url)}
                                onChange={(raw) => {
                                    setSlugTouched(true);
                                    form.setField('slug', slugify(raw));
                                }}
                                onTouch={() => form.touch('slug')}
                                invalid={form.invalid('slug')}
                                error={form.error('slug')}
                            />

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
                                        for this category.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('template')}
                                    </FieldError>
                                </Field>
                            ) : null}

                            {category ? (
                                <CmsRevisionsSection
                                    revisionsCount={category.revisions_count}
                                    revisions={category.revisions}
                                />
                            ) : null}
                    </CmsCollapsibleSidebarCard>

                    <CmsDangerZoneCard
                        show={mode === 'edit' && Boolean(category)}
                        description="Move this category to trash. You can restore it later from the trash tab."
                        onDelete={handleDelete}
                    />
                </div>
            </div>

            <CmsStickyFormFooter
                backHref={route('cms.categories.index')}
                backLabel="Back to Categories"
                submitLabel={submitLabel}
                isCreate={mode === 'create'}
                isDirty={form.isDirty}
                isProcessing={form.processing}
            />
        </form>
    );
}
