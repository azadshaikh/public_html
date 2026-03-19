'use client';

import { router } from '@inertiajs/react';
import { Settings2Icon, ShieldIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { MultiSelectCombobox } from '@/components/ui/multi-select-combobox';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CmsOption,
    MediaPickerPageProps,
    PostEditDetail,
    PostFormValues,
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

type PostFormProps = {
    mode: 'create' | 'edit';
    initialValues?: PostFormValues;
    categoryOptions: CmsOption[];
    tagOptions: CmsOption[];
    authorOptions: CmsOption[];
    metaRobotsOptions: CmsOption[];
    statusOptions: CmsOption[];
    visibilityOptions: CmsOption[];
    templateOptions: CmsOption[];
    preSlug: string;
    baseUrl: string;
    post?: PostEditDetail;
} & MediaPickerPageProps;

const unpublishedCategoryHint =
    'Unpublished categories stay selected but cannot be newly assigned.';

const unpublishedTagHint =
    'Unpublished tags stay selected but cannot be newly assigned.';

const emptyValues: PostFormValues = {
    title: '',
    slug: '',
    content: '',
    excerpt: '',
    feature_image: '',
    is_featured: false,
    status: 'draft',
    visibility: 'public',
    post_password: '',
    password_hint: '',
    author_id: '',
    published_at: '',
    meta_title: '',
    meta_description: '',
    meta_robots: '',
    og_title: '',
    og_description: '',
    og_image: '',
    og_url: '',
    schema: '',
    template: '',
    categories: [],
    tags: [],
};

function normalizeOptionValue(value: CmsOption['value']): number {
    return typeof value === 'number'
        ? value
        : Number.parseInt(String(value), 10);
}

type PostSelectOption = {
    value: string;
    label: string;
    disabled?: boolean;
};

type PostSingleSelectComboboxProps = {
    id?: string;
    value: string | null;
    options: PostSelectOption[];
    onChange: (value: string | null) => void;
    onBlur?: () => void;
    placeholder?: string;
    invalid?: boolean;
    searchable?: boolean;
    disabled?: boolean;
    className?: string;
    emptyMessage?: string;
    searchPlaceholder?: string;
};

function PostSingleSelectCombobox({
    id,
    value,
    options,
    onChange,
    onBlur,
    placeholder = 'Select an option...',
    invalid = false,
    searchable = false,
    disabled = false,
    className,
    emptyMessage = 'No results found.',
    searchPlaceholder = 'Search...',
}: PostSingleSelectComboboxProps) {
    const selectedOption = useMemo(
        () =>
            options.find((option) => String(option.value) === String(value)) ??
            null,
        [options, value],
    );

    return (
        <Combobox
            items={options}
            value={selectedOption}
            disabled={disabled}
            autoComplete={searchable ? 'list' : 'none'}
            itemToStringLabel={(item) => item?.label ?? ''}
            itemToStringValue={(item) =>
                item ? [item.label, item.value].join(' ') : ''
            }
            onValueChange={(newValue) => {
                onChange(newValue ? newValue.value : null);
                onBlur?.();
            }}
        >
            <ComboboxTrigger
                id={id}
                className={
                    !selectedOption
                        ? `text-muted-foreground ${className || ''}`
                        : className
                }
                aria-invalid={invalid || undefined}
                onBlur={onBlur}
                render={
                    <Button
                        type="button"
                        variant="outline"
                        size="comfortable"
                        className="w-full justify-between font-normal"
                    />
                }
            >
                <span className="truncate">
                    {selectedOption?.label ?? placeholder}
                </span>
            </ComboboxTrigger>
            <ComboboxContent>
                {searchable ? (
                    <ComboboxInput
                        placeholder={searchPlaceholder}
                        showTrigger={false}
                        onBlur={onBlur}
                    />
                ) : null}
                <ComboboxEmpty>{emptyMessage}</ComboboxEmpty>
                <ComboboxList>
                    {(option: PostSelectOption) => (
                        <ComboboxItem
                            key={`${id}-${option.value || 'empty'}`}
                            value={option}
                            disabled={option.disabled}
                        >
                            {option.label}
                        </ComboboxItem>
                    )}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    );
}

export default function PostForm({
    mode,
    initialValues,
    categoryOptions,
    tagOptions,
    authorOptions,
    metaRobotsOptions,
    statusOptions,
    visibilityOptions,
    templateOptions,
    preSlug,
    baseUrl,
    post,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
}: PostFormProps) {
    const form = useAppForm<PostFormValues>({
        defaults: initialValues || emptyValues,
        rememberKey:
            mode === 'create'
                ? 'cms.posts.create.form'
                : `cms.posts.edit.${post?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            title: [formValidators.required('Title')],
            status: [formValidators.required('Status')],
            author_id: [formValidators.required('Author')],
            categories: [formValidators.required('Categories')],
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
        () => post?.permalink_url ?? buildPermalink(baseUrl, preSlug, form.data.slug),
        [baseUrl, form.data.slug, post?.permalink_url, preSlug],
    );

    const showPublishAt =
        form.data.status === 'published' || form.data.status === 'scheduled';
    const showPasswordFields = form.data.visibility === 'password';
    const showTemplateField = templateOptions.length > 1;
    const hasMoreOptionsErrors = Boolean(
        form.invalid('slug') ||
            form.invalid('author_id') ||
            form.invalid('template') ||
            form.invalid('post_password') ||
            form.invalid('password_hint'),
    );

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('cms.posts.store')
            : route('cms.posts.update', post!.id);

        const submitLabel = mode === 'create' ? 'Create Post' : 'Save Changes';

    const getArrayFieldError = (field: 'categories' | 'tags') => {
        return (
            form.error(field) ??
            form.errors[`${field}.0` as keyof typeof form.errors] ??
            undefined
        );
    };

    const categorySelectOptions = useMemo(
        () =>
            categoryOptions.map((option) => ({
                value: normalizeOptionValue(option.value),
                label: option.label,
                disabled: option.disabled,
                description: option.disabled
                    ? unpublishedCategoryHint
                    : undefined,
            })),
        [categoryOptions],
    );
    const tagSelectOptions = useMemo(
        () =>
            tagOptions.map((option) => ({
                value: normalizeOptionValue(option.value),
                label: option.label,
                disabled: option.disabled,
                description: option.disabled ? unpublishedTagHint : undefined,
            })),
        [tagOptions],
    );
    const authorSelectOptions = useMemo(
        () => [
            { value: '', label: 'Select author' },
            ...authorOptions.map((option) => ({
                value: String(option.value),
                label: option.label,
                disabled: option.disabled,
            })),
        ],
        [authorOptions],
    );
    const statusSelectOptions = useMemo(
        () =>
            statusOptions.map((option) => ({
                value: String(option.value),
                label: option.label,
                disabled: option.disabled,
            })),
        [statusOptions],
    );
    const visibilitySelectOptions = useMemo(
        () =>
            visibilityOptions.map((option) => ({
                value: String(option.value),
                label: option.label,
                disabled: option.disabled,
            })),
        [visibilityOptions],
    );
    const templateSelectOptions = useMemo(
        () =>
            templateOptions.map((option) => ({
                value: String(option.value),
                label: option.label,
                disabled: option.disabled,
            })),
        [templateOptions],
    );

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title: mode === 'create' ? 'Post created' : 'Post updated',
                description:
                    mode === 'create'
                        ? 'The post has been created successfully.'
                        : 'The post has been updated successfully.',
            },
        });
    };

    const handleDelete = () => {
        if (!post) {
            return;
        }

        if (!window.confirm(`Move "${post.title}" to trash?`)) {
            return;
        }

        router.delete(route('cms.posts.destroy', post.id), {
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
                            placeholder="Enter post title"
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
                                        contentPlaceholder="Write the full post content"
                                        updatedAtHuman={
                                            post?.updated_at_human
                                        }
                                        updatedAtFormatted={
                                            post?.updated_at_formatted
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
                                        ogUrlPlaceholder="https://example.com/your-post"
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
                        previewUrl={post?.featured_image_url}
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
                                ? route('cms.posts.create')
                                : route('cms.posts.edit', post!.id)
                        }
                    />

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2Icon className="size-4 text-muted-foreground" />
                                <CardTitle>Publish settings</CardTitle>
                            </div>
                            <CardDescription>
                                Control publishing, taxonomy, and visibility.
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
                                        value={form.data.status}
                                        className="w-full"
                                        aria-invalid={
                                            form.invalid('status') || undefined
                                        }
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
                                    >
                                        {statusSelectOptions.map((option) => (
                                            <NativeSelectOption
                                                key={`status-${option.value}`}
                                                value={option.value}
                                                disabled={option.disabled}
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
                                        value={form.data.visibility}
                                        className="w-full"
                                        aria-invalid={
                                            form.invalid('visibility') ||
                                            undefined
                                        }
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
                                    >
                                        {visibilitySelectOptions.map(
                                            (option) => (
                                                <NativeSelectOption
                                                    key={`visibility-${option.value}`}
                                                    value={option.value}
                                                    disabled={option.disabled}
                                                >
                                                    {option.label}
                                                </NativeSelectOption>
                                            ),
                                        )}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('visibility')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

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

                            <Field
                                data-invalid={
                                    form.invalid('categories') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="categories">
                                    Category
                                </RequiredLabel>
                                <FieldDescription>
                                    Select a category for this post.
                                </FieldDescription>
                                <NativeSelect
                                    id="categories"
                                    value={
                                        form.data.categories.length > 0
                                            ? String(form.data.categories[0])
                                            : ''
                                    }
                                    className="w-full"
                                    aria-invalid={
                                        form.invalid('categories') || undefined
                                    }
                                    onChange={(event) =>
                                        form.setField(
                                            'categories',
                                            event.target.value
                                                ? [
                                                      Number.parseInt(
                                                          event.target.value,
                                                          10,
                                                      ),
                                                  ]
                                                : [],
                                        )
                                    }
                                    onBlur={() => form.touch('categories')}
                                >
                                    <NativeSelectOption value="">
                                        Select category
                                    </NativeSelectOption>
                                    {categorySelectOptions.map((option) => (
                                        <NativeSelectOption
                                            key={`category-${option.value}`}
                                            value={String(option.value)}
                                            disabled={option.disabled}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>
                                    {getArrayFieldError('categories')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={form.invalid('tags') || undefined}
                            >
                                <FieldLabel htmlFor="tags">Tags</FieldLabel>
                                <FieldDescription>
                                    Tags are optional and help organize related
                                    content.
                                </FieldDescription>
                                <MultiSelectCombobox
                                    id="tags"
                                    value={form.data.tags}
                                    options={tagSelectOptions}
                                    onValueChange={(value) =>
                                        form.setField('tags', value)
                                    }
                                    onBlur={() => form.touch('tags')}
                                    placeholder="Select tags"
                                    emptyMessage="No tags found."
                                    size="comfortable"
                                    aria-invalid={
                                        form.invalid('tags') || undefined
                                    }
                                />
                                <FieldError>
                                    {getArrayFieldError('tags')}
                                </FieldError>
                            </Field>
                        </CardContent>
                    </Card>

                    <CmsCollapsibleSidebarCard
                        title="More options"
                        description="Fine-tune permalink, author, templates, and password access."
                        icon={ShieldIcon}
                        hasErrors={hasMoreOptionsErrors}
                    >
                            <Field orientation="horizontal">
                                <Switch
                                    checked={form.data.is_featured}
                                    onCheckedChange={(checked) =>
                                        form.setField('is_featured', checked)
                                    }
                                />
                                <div className="flex flex-col gap-1">
                                    <FieldLabel htmlFor="is_featured">
                                        Featured post
                                    </FieldLabel>
                                    <FieldDescription>
                                        Featured posts stay pinned above other
                                        posts.
                                    </FieldDescription>
                                </div>
                            </Field>

                            <CmsSlugField
                                value={form.data.slug}
                                preSlug={preSlug}
                                permalinkPreview={permalinkPreview}
                                hasPermalink={Boolean(post?.permalink_url)}
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
                                <PostSingleSelectCombobox
                                    id="author_id"
                                    value={String(form.data.author_id)}
                                    options={authorSelectOptions}
                                    onChange={(value) =>
                                        form.setField(
                                            'author_id',
                                            value === ''
                                                ? ''
                                                : Number.parseInt(
                                                      String(value),
                                                      10,
                                                  ),
                                        )
                                    }
                                    onBlur={() => form.touch('author_id')}
                                    placeholder="Select author"
                                    searchable
                                    searchPlaceholder="Search authors..."
                                    emptyMessage="No author found."
                                    invalid={
                                        form.invalid('author_id') || undefined
                                    }
                                />
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
                                    <PostSingleSelectCombobox
                                        id="template"
                                        value={form.data.template}
                                        options={templateSelectOptions}
                                        onChange={(value) =>
                                            form.setField(
                                                'template',
                                                value ?? '',
                                            )
                                        }
                                        onBlur={() => form.touch('template')}
                                        placeholder="Select template"
                                        invalid={
                                            form.invalid('template') ||
                                            undefined
                                        }
                                    />
                                    <FieldDescription>
                                        Choose a different presentation template
                                        for this post.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('template')}
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
                                                post?.is_password_protected
                                                    ? 'Leave blank to keep the current password'
                                                    : 'Enter password'
                                            }
                                        />
                                        <FieldDescription>
                                            {post?.is_password_protected
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

                            {post ? (
                                <CmsRevisionsSection
                                    revisionsCount={post.revisions_count}
                                    revisions={post.revisions}
                                />
                            ) : null}
                    </CmsCollapsibleSidebarCard>

                    <CmsDangerZoneCard
                        show={mode === 'edit' && Boolean(post)}
                        description="Move this post to trash. You can restore it later from the trash tab."
                        onDelete={handleDelete}
                    />
                </div>
            </div>

            <CmsStickyFormFooter
                backHref={route('cms.posts.index')}
                backLabel="Back to Posts"
                submitLabel={submitLabel}
                isCreate={mode === 'create'}
                isDirty={form.isDirty}
                isProcessing={form.processing}
            />
        </form>
    );
}
