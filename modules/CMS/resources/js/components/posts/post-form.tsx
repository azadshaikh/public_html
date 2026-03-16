'use client';

import { Link, router } from '@inertiajs/react';
import {
    ExternalLinkIcon,
    SaveIcon,
    Settings2Icon,
    ShieldIcon,
    Trash2Icon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { AsteroNote } from '@/components/asteronote/asteronote';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { MediaPickerField } from '@/components/media/media-picker-field';
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
    PanelTabs,
    PanelTabsContent,
    PanelTabsList,
    PanelTabsTrigger,
} from '@/components/ui/panel-tabs';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CmsOption,
    MediaPickerPageProps,
    PostEditDetail,
    PostFormValues,
} from '../../types/cms';

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

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
}

function normalizeOptionValue(value: CmsOption['value']): number {
    return typeof value === 'number' ? value : Number.parseInt(String(value), 10);
}

function buildPermalink(baseUrl: string, preSlug: string, slug: string): string {
    const base = baseUrl.replace(/\/$/, '');
    const cleanedSlug = slug.trim() === '' ? 'your-slug-here' : slug.trim();

    if (preSlug === '/' || preSlug.trim() === '') {
        return `${base}/${cleanedSlug}`;
    }

    const prefix = preSlug.replace(/^\/+|\/+$/g, '');

    return `${base}/${prefix}/${cleanedSlug}`;
}

function RequiredLabel({
    htmlFor,
    children,
}: {
    htmlFor?: string;
    children: string;
}) {
    return (
        <FieldLabel htmlFor={htmlFor}>
            {children} <span className="text-destructive">*</span>
        </FieldLabel>
    );
}

type PostSelectOption = {
    value: string;
    label: string;
    disabled?: boolean;
};

function PostSingleSelectCombobox({
    id,
    value,
    options,
    placeholder,
    searchable = false,
    searchPlaceholder = 'Search...',
    emptyMessage = 'No results found.',
    onValueChange,
    onBlur,
    invalid,
}: {
    id: string;
    value: string;
    options: PostSelectOption[];
    placeholder: string;
    searchable?: boolean;
    searchPlaceholder?: string;
    emptyMessage?: string;
    onValueChange: (value: string) => void;
    onBlur?: () => void;
    invalid?: boolean;
}) {
    const selectedOption =
        options.find((option) => option.value === value) ?? null;

    return (
        <Combobox
            items={options}
            value={selectedOption}
            autoHighlight
            itemToStringLabel={(item) => item?.label ?? ''}
            itemToStringValue={(item) =>
                item ? `${item.label} ${item.value}`.trim() : ''
            }
            onValueChange={(item) => {
                onValueChange(item?.value ?? '');
                onBlur?.();
            }}
        >
            <ComboboxTrigger
                id={id}
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
                className={!selectedOption ? 'text-muted-foreground' : undefined}
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
}: PostFormProps) {
    const form = useAppForm<PostFormValues>({
        defaults: initialValues || emptyValues,
        rememberKey:
            mode === 'create' ? 'cms.posts.create.form' : `cms.posts.edit.${post?.id}`,
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
        () => buildPermalink(baseUrl, preSlug, form.data.slug),
        [baseUrl, form.data.slug, preSlug],
    );

    const showPublishAt =
        form.data.status === 'published' || form.data.status === 'scheduled';
    const showPasswordFields = form.data.visibility === 'password';
    const showTemplateField = templateOptions.length > 1;

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
                description: option.disabled ? unpublishedCategoryHint : undefined,
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
        () =>
            [
                { value: '', label: 'Select author' },
                ...authorOptions.map((option) => ({
                    value: String(option.value),
                    label: option.label,
                    disabled: option.disabled,
                })),
            ],
        [authorOptions],
    );
    const metaRobotsSelectOptions = useMemo(
        () =>
            metaRobotsOptions.map((option) => ({
                value: String(option.value),
                label: option.label,
                disabled: option.disabled,
            })),
        [metaRobotsOptions],
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
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex flex-col gap-6">
                    <Field data-invalid={form.invalid('title') || undefined}>
                        <RequiredLabel htmlFor="title">Title</RequiredLabel>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) => form.setField('title', event.target.value)}
                            onBlur={() => form.touch('title')}
                            aria-invalid={form.invalid('title') || undefined}
                            placeholder="Enter post title"
                        />
                        <FieldError>{form.error('title')}</FieldError>
                    </Field>

                    <PanelTabs defaultValue="content">
                        <PanelTabsList>
                            <PanelTabsTrigger value="content">Content</PanelTabsTrigger>
                            <PanelTabsTrigger value="seo">SEO</PanelTabsTrigger>
                            <PanelTabsTrigger value="social">Social</PanelTabsTrigger>
                            <PanelTabsTrigger value="schema">Schema</PanelTabsTrigger>
                        </PanelTabsList>

                        <PanelTabsContent value="content" className="flex flex-col gap-6">
                            <Field data-invalid={form.invalid('content') || undefined}>
                                <FieldLabel htmlFor="content">Content</FieldLabel>
                                <AsteroNote
                                    id="content"
                                    value={form.data.content}
                                    onChange={(value) => form.setField('content', value)}
                                    onBlur={() => form.touch('content')}
                                    placeholder="Write the full post content"
                                    invalid={form.invalid('content') || undefined}
                                />
                                <FieldError>{form.error('content')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('excerpt') || undefined}>
                                <FieldLabel htmlFor="excerpt">Excerpt</FieldLabel>
                                <Textarea
                                    id="excerpt"
                                    rows={4}
                                    value={form.data.excerpt}
                                    onChange={(event) =>
                                        form.setField('excerpt', event.target.value)
                                    }
                                    onBlur={() => form.touch('excerpt')}
                                    aria-invalid={form.invalid('excerpt') || undefined}
                                    placeholder="Enter a short excerpt"
                                />
                                <FieldDescription>
                                    Used in listings, previews, and search snippets.
                                </FieldDescription>
                                <FieldError>{form.error('excerpt')}</FieldError>
                            </Field>

                            {post ? (
                                <div className="text-sm text-muted-foreground">
                                    Last updated {post.updated_at_human ?? 'recently'}
                                    {post.updated_at_formatted
                                        ? ` (${post.updated_at_formatted})`
                                        : ''}
                                </div>
                            ) : null}
                        </PanelTabsContent>

                        <PanelTabsContent value="seo" className="flex flex-col gap-6">
                            <Field data-invalid={form.invalid('meta_title') || undefined}>
                                <FieldLabel htmlFor="meta_title">Meta title</FieldLabel>
                                <Input
                                    id="meta_title"
                                    value={form.data.meta_title}
                                    onChange={(event) =>
                                        form.setField('meta_title', event.target.value)
                                    }
                                    onBlur={() => form.touch('meta_title')}
                                    aria-invalid={form.invalid('meta_title') || undefined}
                                    placeholder="Enter meta title"
                                />
                                <FieldDescription>
                                    Recommended length: 50–60 characters.
                                </FieldDescription>
                                <FieldError>{form.error('meta_title')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('meta_description') || undefined}>
                                <FieldLabel htmlFor="meta_description">
                                    Meta description
                                </FieldLabel>
                                <Textarea
                                    id="meta_description"
                                    rows={4}
                                    value={form.data.meta_description}
                                    onChange={(event) =>
                                        form.setField('meta_description', event.target.value)
                                    }
                                    onBlur={() => form.touch('meta_description')}
                                    aria-invalid={
                                        form.invalid('meta_description') || undefined
                                    }
                                    placeholder="Enter meta description"
                                />
                                <FieldError>{form.error('meta_description')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('meta_robots') || undefined}>
                                <FieldLabel htmlFor="meta_robots">Meta robots</FieldLabel>
                                <PostSingleSelectCombobox
                                    id="meta_robots"
                                    value={form.data.meta_robots}
                                    options={metaRobotsSelectOptions}
                                    onValueChange={(value) => form.setField('meta_robots', value)}
                                    onBlur={() => form.touch('meta_robots')}
                                    placeholder="Select meta robots"
                                    invalid={form.invalid('meta_robots') || undefined}
                                />
                                <FieldError>{form.error('meta_robots')}</FieldError>
                            </Field>
                        </PanelTabsContent>

                        <PanelTabsContent value="social" className="flex flex-col gap-6">
                            <Field data-invalid={form.invalid('og_title') || undefined}>
                                <FieldLabel htmlFor="og_title">Open Graph title</FieldLabel>
                                <Input
                                    id="og_title"
                                    value={form.data.og_title}
                                    onChange={(event) =>
                                        form.setField('og_title', event.target.value)
                                    }
                                    onBlur={() => form.touch('og_title')}
                                    aria-invalid={form.invalid('og_title') || undefined}
                                    placeholder="Enter Open Graph title"
                                />
                                <FieldError>{form.error('og_title')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('og_description') || undefined}>
                                <FieldLabel htmlFor="og_description">
                                    Open Graph description
                                </FieldLabel>
                                <Textarea
                                    id="og_description"
                                    rows={4}
                                    value={form.data.og_description}
                                    onChange={(event) =>
                                        form.setField('og_description', event.target.value)
                                    }
                                    onBlur={() => form.touch('og_description')}
                                    aria-invalid={form.invalid('og_description') || undefined}
                                    placeholder="Enter Open Graph description"
                                />
                                <FieldError>{form.error('og_description')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('og_image') || undefined}>
                                <FieldLabel htmlFor="og_image">Open Graph image</FieldLabel>
                                <Input
                                    id="og_image"
                                    type="url"
                                    value={form.data.og_image}
                                    onChange={(event) =>
                                        form.setField('og_image', event.target.value)
                                    }
                                    onBlur={() => form.touch('og_image')}
                                    aria-invalid={form.invalid('og_image') || undefined}
                                    placeholder="https://example.com/social-image.jpg"
                                />
                                <FieldDescription>
                                    Paste an image URL or choose one from the media library.
                                </FieldDescription>
                                <FieldError>{form.error('og_image')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('og_url') || undefined}>
                                <FieldLabel htmlFor="og_url">Open Graph URL</FieldLabel>
                                <Input
                                    id="og_url"
                                    type="url"
                                    value={form.data.og_url}
                                    onChange={(event) =>
                                        form.setField('og_url', event.target.value)
                                    }
                                    onBlur={() => form.touch('og_url')}
                                    aria-invalid={form.invalid('og_url') || undefined}
                                    placeholder="https://example.com/your-post"
                                />
                                <FieldError>{form.error('og_url')}</FieldError>
                            </Field>
                        </PanelTabsContent>

                        <PanelTabsContent value="schema" className="flex flex-col gap-4">
                            <Field data-invalid={form.invalid('schema') || undefined}>
                                <FieldLabel htmlFor="schema">Schema markup</FieldLabel>
                                <MonacoEditor
                                    name="schema"
                                    language="html"
                                    height={360}
                                    value={form.data.schema}
                                    onChange={(value) => form.setField('schema', value)}
                                    onBlur={() => form.touch('schema')}
                                    placeholder="Add custom schema markup"
                                />
                                <FieldDescription>
                                    Optional structured data for search engines.
                                </FieldDescription>
                                <FieldError>{form.error('schema')}</FieldError>
                            </Field>
                        </PanelTabsContent>
                    </PanelTabs>
                </div>

                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Featured image</CardTitle>
                            <CardDescription>
                                Choose an image from the media library or upload a new one.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <MediaPickerField
                                value={form.data.feature_image || null}
                                previewUrl={post?.featured_image_url}
                                onChange={(item) => {
                                    form.setField(
                                        'feature_image',
                                        item ? item.id : '',
                                    );
                                    form.touch('feature_image');
                                }}
                                dialogTitle="Select featured image"
                                selectLabel="Select featured image"
                                aria-invalid={
                                    form.invalid('feature_image') || undefined
                                }
                                pickerMedia={pickerMedia}
                                pickerFilters={pickerFilters}
                                uploadSettings={uploadSettings}
                                pickerAction={
                                    mode === 'create'
                                        ? route('cms.posts.create')
                                        : route('cms.posts.edit', post!.id)
                                }
                            />
                            <FieldError>{form.error('feature_image')}</FieldError>
                        </CardContent>
                    </Card>

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
                                <Field data-invalid={form.invalid('status') || undefined}>
                                    <RequiredLabel htmlFor="status">Status</RequiredLabel>
                                    <PostSingleSelectCombobox
                                        id="status"
                                        value={form.data.status}
                                        options={statusSelectOptions}
                                        onValueChange={(value) => {
                                            const nextStatus = value;
                                            form.setField('status', nextStatus);

                                            if (
                                                nextStatus !== 'published' &&
                                                nextStatus !== 'scheduled'
                                            ) {
                                                form.setField('published_at', '');
                                            }
                                        }}
                                        onBlur={() => form.touch('status')}
                                        placeholder="Select status"
                                        invalid={form.invalid('status') || undefined}
                                    />
                                    <FieldError>{form.error('status')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('visibility') || undefined}>
                                    <FieldLabel htmlFor="visibility">Visibility</FieldLabel>
                                    <PostSingleSelectCombobox
                                        id="visibility"
                                        value={form.data.visibility}
                                        options={visibilitySelectOptions}
                                        onValueChange={(value) => {
                                            const nextVisibility = value;
                                            form.setField('visibility', nextVisibility);

                                            if (nextVisibility !== 'password') {
                                                form.setField('post_password', '');
                                                form.setField('password_hint', '');
                                            }
                                        }}
                                        onBlur={() => form.touch('visibility')}
                                        placeholder="Select visibility"
                                        invalid={form.invalid('visibility') || undefined}
                                    />
                                    <FieldError>{form.error('visibility')}</FieldError>
                                </Field>
                            </FieldGroup>

                            {showPublishAt ? (
                                <Field data-invalid={form.invalid('published_at') || undefined}>
                                    <FieldLabel htmlFor="published_at">Publish at</FieldLabel>
                                    <Input
                                        id="published_at"
                                        type="datetime-local"
                                        value={form.data.published_at}
                                        onChange={(event) =>
                                            form.setField('published_at', event.target.value)
                                        }
                                        onBlur={() => form.touch('published_at')}
                                        aria-invalid={
                                            form.invalid('published_at') || undefined
                                        }
                                    />
                                    <FieldDescription>
                                        {form.data.status === 'scheduled'
                                            ? 'Choose a future date and time for scheduling.'
                                            : 'Choose the publish date and time.'}
                                    </FieldDescription>
                                    <FieldError>{form.error('published_at')}</FieldError>
                                </Field>
                            ) : null}

                            <Field data-invalid={form.invalid('categories') || undefined}>
                                <RequiredLabel htmlFor="categories">Categories</RequiredLabel>
                                <FieldDescription>
                                    Select at least one category for this post.
                                </FieldDescription>
                                <MultiSelectCombobox
                                    id="categories"
                                    value={form.data.categories}
                                    options={categorySelectOptions}
                                    onValueChange={(value) =>
                                        form.setField('categories', value)
                                    }
                                    onBlur={() => form.touch('categories')}
                                    placeholder="Select categories"
                                    emptyMessage="No categories found."
                                    size="comfortable"
                                    aria-invalid={form.invalid('categories') || undefined}
                                />
                                <FieldError>{getArrayFieldError('categories')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('tags') || undefined}>
                                <FieldLabel htmlFor="tags">Tags</FieldLabel>
                                <FieldDescription>
                                    Tags are optional and help organize related content.
                                </FieldDescription>
                                <MultiSelectCombobox
                                    id="tags"
                                    value={form.data.tags}
                                    options={tagSelectOptions}
                                    onValueChange={(value) => form.setField('tags', value)}
                                    onBlur={() => form.touch('tags')}
                                    placeholder="Select tags"
                                    emptyMessage="No tags found."
                                    size="comfortable"
                                    aria-invalid={form.invalid('tags') || undefined}
                                />
                                <FieldError>{getArrayFieldError('tags')}</FieldError>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ShieldIcon className="size-4 text-muted-foreground" />
                                <CardTitle>More options</CardTitle>
                            </div>
                            <CardDescription>
                                Fine-tune permalink, author, templates, and password access.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
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
                                        Featured posts stay pinned above other posts.
                                    </FieldDescription>
                                </div>
                            </Field>

                            <Field data-invalid={form.invalid('slug') || undefined}>
                                <FieldLabel htmlFor="slug">Permalink</FieldLabel>
                                <div className="flex items-center rounded-lg border bg-muted/20 pl-3">
                                    <span className="shrink-0 text-sm text-muted-foreground">
                                        {preSlug}
                                    </span>
                                    <Input
                                        id="slug"
                                        className="border-0 bg-transparent ring-0 focus-visible:border-0 focus-visible:ring-0"
                                        value={form.data.slug}
                                        onChange={(event) => {
                                            setSlugTouched(true);
                                            form.setField('slug', slugify(event.target.value));
                                        }}
                                        onBlur={() => form.touch('slug')}
                                        aria-invalid={form.invalid('slug') || undefined}
                                        placeholder="auto-generated-from-title"
                                    />
                                </div>
                                {post?.permalink_url ? (
                                    <a
                                        href={permalinkPreview}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                    >
                                        <span className="truncate">{permalinkPreview}</span>
                                        <ExternalLinkIcon className="size-3.5 shrink-0" />
                                    </a>
                                ) : (
                                    <FieldDescription>{permalinkPreview}</FieldDescription>
                                )}
                                <FieldError>{form.error('slug')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('author_id') || undefined}>
                                <RequiredLabel htmlFor="author_id">Author</RequiredLabel>
                                <PostSingleSelectCombobox
                                    id="author_id"
                                    value={String(form.data.author_id)}
                                    options={authorSelectOptions}
                                    onValueChange={(value) =>
                                        form.setField(
                                            'author_id',
                                            value === ''
                                                ? ''
                                                : Number.parseInt(String(value), 10),
                                        )
                                    }
                                    onBlur={() => form.touch('author_id')}
                                    placeholder="Select author"
                                    searchable
                                    searchPlaceholder="Search authors..."
                                    emptyMessage="No author found."
                                    invalid={form.invalid('author_id') || undefined}
                                />
                                <FieldError>{form.error('author_id')}</FieldError>
                            </Field>

                            {showTemplateField ? (
                                <Field data-invalid={form.invalid('template') || undefined}>
                                    <FieldLabel htmlFor="template">Template</FieldLabel>
                                    <PostSingleSelectCombobox
                                        id="template"
                                        value={form.data.template}
                                        options={templateSelectOptions}
                                        onValueChange={(value) => form.setField('template', value)}
                                        onBlur={() => form.touch('template')}
                                        placeholder="Select template"
                                        invalid={form.invalid('template') || undefined}
                                    />
                                    <FieldDescription>
                                        Choose a different presentation template for this post.
                                    </FieldDescription>
                                    <FieldError>{form.error('template')}</FieldError>
                                </Field>
                            ) : null}

                            {showPasswordFields ? (
                                <>
                                    <Field
                                        data-invalid={
                                            form.invalid('post_password') || undefined
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
                                            onBlur={() => form.touch('post_password')}
                                            aria-invalid={
                                                form.invalid('post_password') || undefined
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
                                            form.invalid('password_hint') || undefined
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
                                            onBlur={() => form.touch('password_hint')}
                                            aria-invalid={
                                                form.invalid('password_hint') || undefined
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

                    <Card>
                        <CardHeader>
                            <CardTitle>{submitLabel}</CardTitle>
                            <CardDescription>
                                Save this post and return to the editor.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter className="flex-col gap-3">
                            <Button type="submit" className="w-full" disabled={form.processing}>
                                {form.processing ? (
                                    <Spinner className="size-4" />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                {submitLabel}
                            </Button>
                            <Button type="button" variant="outline" className="w-full" asChild>
                                <Link href={route('cms.posts.index')}>Back to Posts</Link>
                            </Button>
                        </CardFooter>
                    </Card>

                    {mode === 'edit' && post ? (
                        <Card className="border-destructive/30">
                            <CardHeader>
                                <CardTitle>Danger zone</CardTitle>
                                <CardDescription>
                                    Move this post to trash. You can restore it later from the
                                    trash tab.
                                </CardDescription>
                            </CardHeader>
                            <CardFooter>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    className="w-full"
                                    onClick={handleDelete}
                                >
                                    <Trash2Icon data-icon="inline-start" />
                                    Move to Trash
                                </Button>
                            </CardFooter>
                        </Card>
                    ) : null}
                </div>
            </div>
        </form>
    );
}
