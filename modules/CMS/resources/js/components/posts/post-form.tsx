'use client';

import { Link, router } from '@inertiajs/react';
import {
    ArrowUpRightIcon,
    ExternalLinkIcon,
    ImageIcon,
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
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import { cn } from '@/lib/utils';
import type {
    CmsOption,
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
};

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

    const toggleSelection = (field: 'categories' | 'tags', value: number) => {
        const currentValues = form.data[field];
        const nextValues = currentValues.includes(value)
            ? currentValues.filter((currentValue) => currentValue !== value)
            : [...currentValues, value];

        form.setField(field, nextValues);
        form.touch(field);
    };

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
                    <Card>
                        <CardHeader>
                            <CardTitle>Post content</CardTitle>
                            <CardDescription>
                                Write the main content, summary, and SEO metadata for this
                                post.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
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

                            <Tabs defaultValue="content" size="comfortable" className="gap-4">
                                <TabsList variant="line">
                                    <TabsTrigger value="content">Content</TabsTrigger>
                                    <TabsTrigger value="seo">SEO</TabsTrigger>
                                    <TabsTrigger value="social">Social</TabsTrigger>
                                    <TabsTrigger value="schema">Schema</TabsTrigger>
                                </TabsList>

                                <TabsContent value="content" className="flex flex-col gap-6">
                                    <Field data-invalid={form.invalid('content') || undefined}>
                                        <FieldLabel htmlFor="content">Content</FieldLabel>
                                        <AsteroNote
                                            id="content"
                                            value={form.data.content}
                                            onChange={(value) =>
                                                form.setField('content', value)
                                            }
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
                                            aria-invalid={
                                                form.invalid('excerpt') || undefined
                                            }
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
                                </TabsContent>

                                <TabsContent value="seo" className="flex flex-col gap-6">
                                    <Field data-invalid={form.invalid('meta_title') || undefined}>
                                        <FieldLabel htmlFor="meta_title">Meta title</FieldLabel>
                                        <Input
                                            id="meta_title"
                                            value={form.data.meta_title}
                                            onChange={(event) =>
                                                form.setField('meta_title', event.target.value)
                                            }
                                            onBlur={() => form.touch('meta_title')}
                                            aria-invalid={
                                                form.invalid('meta_title') || undefined
                                            }
                                            placeholder="Enter meta title"
                                        />
                                        <FieldDescription>
                                            Recommended length: 50–60 characters.
                                        </FieldDescription>
                                        <FieldError>{form.error('meta_title')}</FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('meta_description') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="meta_description">
                                            Meta description
                                        </FieldLabel>
                                        <Textarea
                                            id="meta_description"
                                            rows={4}
                                            value={form.data.meta_description}
                                            onChange={(event) =>
                                                form.setField(
                                                    'meta_description',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('meta_description')
                                            }
                                            aria-invalid={
                                                form.invalid('meta_description') ||
                                                undefined
                                            }
                                            placeholder="Enter meta description"
                                        />
                                        <FieldError>
                                            {form.error('meta_description')}
                                        </FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('meta_robots') || undefined}>
                                        <FieldLabel htmlFor="meta_robots">Meta robots</FieldLabel>
                                        <NativeSelect
                                            id="meta_robots"
                                            className="w-full"
                                            value={form.data.meta_robots}
                                            onChange={(event) =>
                                                form.setField('meta_robots', event.target.value)
                                            }
                                            onBlur={() => form.touch('meta_robots')}
                                            aria-invalid={
                                                form.invalid('meta_robots') || undefined
                                            }
                                        >
                                            {metaRobotsOptions.map((option) => (
                                                <NativeSelectOption
                                                    key={String(option.value)}
                                                    value={String(option.value)}
                                                >
                                                    {option.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                        <FieldError>{form.error('meta_robots')}</FieldError>
                                    </Field>
                                </TabsContent>

                                <TabsContent value="social" className="flex flex-col gap-6">
                                    <Field data-invalid={form.invalid('og_title') || undefined}>
                                        <FieldLabel htmlFor="og_title">
                                            Open Graph title
                                        </FieldLabel>
                                        <Input
                                            id="og_title"
                                            value={form.data.og_title}
                                            onChange={(event) =>
                                                form.setField('og_title', event.target.value)
                                            }
                                            onBlur={() => form.touch('og_title')}
                                            aria-invalid={
                                                form.invalid('og_title') || undefined
                                            }
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
                                                form.setField(
                                                    'og_description',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() => form.touch('og_description')}
                                            aria-invalid={
                                                form.invalid('og_description') || undefined
                                            }
                                            placeholder="Enter Open Graph description"
                                        />
                                        <FieldError>
                                            {form.error('og_description')}
                                        </FieldError>
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
                                            aria-invalid={
                                                form.invalid('og_image') || undefined
                                            }
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
                                            aria-invalid={
                                                form.invalid('og_url') || undefined
                                            }
                                            placeholder="https://example.com/your-post"
                                        />
                                        <FieldError>{form.error('og_url')}</FieldError>
                                    </Field>
                                </TabsContent>

                                <TabsContent value="schema" className="flex flex-col gap-4">
                                    <Field data-invalid={form.invalid('schema') || undefined}>
                                        <FieldLabel htmlFor="schema">Schema markup</FieldLabel>
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
                                            Optional structured data for search engines.
                                        </FieldDescription>
                                        <FieldError>{form.error('schema')}</FieldError>
                                    </Field>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Featured image</CardTitle>
                            <CardDescription>
                                Reference a media item ID from the media library.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {post?.featured_image_url ? (
                                <div className="overflow-hidden rounded-lg border bg-muted/30">
                                    <img
                                        src={post.featured_image_url}
                                        alt="Featured preview"
                                        className="aspect-video w-full object-cover"
                                    />
                                </div>
                            ) : (
                                <div className="flex aspect-video items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                                    <ImageIcon className="mr-2 size-4" />
                                    No featured image selected
                                </div>
                            )}

                            <Field data-invalid={form.invalid('feature_image') || undefined}>
                                <FieldLabel htmlFor="feature_image">Media ID</FieldLabel>
                                <Input
                                    id="feature_image"
                                    type="number"
                                    min={1}
                                    value={form.data.feature_image}
                                    onChange={(event) =>
                                        form.setField(
                                            'feature_image',
                                            event.target.value === ''
                                                ? ''
                                                : Number.parseInt(
                                                      event.target.value,
                                                      10,
                                                  ),
                                        )
                                    }
                                    onBlur={() => form.touch('feature_image')}
                                    aria-invalid={
                                        form.invalid('feature_image') || undefined
                                    }
                                    placeholder="Enter media item ID"
                                />
                                <FieldDescription>
                                    Open the media library in a new tab to copy the media ID.
                                </FieldDescription>
                                <FieldError>{form.error('feature_image')}</FieldError>
                            </Field>
                        </CardContent>
                        <CardFooter>
                            <Button variant="outline" asChild className="w-full">
                                <a
                                    href={route('app.media-library.index')}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ArrowUpRightIcon data-icon="inline-start" />
                                    Open media library
                                </a>
                            </Button>
                        </CardFooter>
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
                                    <NativeSelect
                                        id="status"
                                        className="w-full"
                                        value={form.data.status}
                                        onChange={(event) => {
                                            const nextStatus = event.target.value;
                                            form.setField('status', nextStatus);

                                            if (
                                                nextStatus !== 'published' &&
                                                nextStatus !== 'scheduled'
                                            ) {
                                                form.setField('published_at', '');
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
                                    <FieldError>{form.error('status')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('visibility') || undefined}>
                                    <FieldLabel htmlFor="visibility">Visibility</FieldLabel>
                                    <NativeSelect
                                        id="visibility"
                                        className="w-full"
                                        value={form.data.visibility}
                                        onChange={(event) => {
                                            const nextVisibility = event.target.value;
                                            form.setField('visibility', nextVisibility);

                                            if (nextVisibility !== 'password') {
                                                form.setField('post_password', '');
                                                form.setField('password_hint', '');
                                            }
                                        }}
                                        onBlur={() => form.touch('visibility')}
                                        aria-invalid={
                                            form.invalid('visibility') || undefined
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

                            <FieldSet>
                                <FieldLegend>Categories</FieldLegend>
                                <FieldDescription>
                                    Select at least one category for this post.
                                </FieldDescription>
                                <ScrollArea className="max-h-56 rounded-lg border">
                                    <div className="flex flex-col gap-3 p-3">
                                        {categoryOptions.map((option) => {
                                            const value = normalizeOptionValue(option.value);
                                            const checked = form.data.categories.includes(value);

                                            return (
                                                <label
                                                    key={`category-${value}`}
                                                    className={cn(
                                                        'flex items-start gap-3 rounded-lg border p-3 text-sm',
                                                        checked
                                                            ? 'border-primary/30 bg-primary/5'
                                                            : 'border-border',
                                                        option.disabled
                                                            ? 'opacity-60'
                                                            : 'cursor-pointer',
                                                    )}
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        disabled={option.disabled}
                                                        onCheckedChange={() =>
                                                            toggleSelection(
                                                                'categories',
                                                                value,
                                                            )
                                                        }
                                                        aria-invalid={
                                                            form.invalid('categories') ||
                                                            undefined
                                                        }
                                                    />
                                                    <div className="flex flex-col gap-1">
                                                        <span className="font-medium">
                                                            {option.label}
                                                        </span>
                                                        {option.disabled ? (
                                                            <span className="text-xs text-muted-foreground">
                                                                Unpublished categories stay selected but
                                                                cannot be newly assigned.
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </ScrollArea>
                                <FieldError>{getArrayFieldError('categories')}</FieldError>
                            </FieldSet>

                            <FieldSet>
                                <FieldLegend>Tags</FieldLegend>
                                <FieldDescription>
                                    Tags are optional and help organize related content.
                                </FieldDescription>
                                <ScrollArea className="max-h-56 rounded-lg border">
                                    <div className="flex flex-col gap-3 p-3">
                                        {tagOptions.map((option) => {
                                            const value = normalizeOptionValue(option.value);
                                            const checked = form.data.tags.includes(value);

                                            return (
                                                <label
                                                    key={`tag-${value}`}
                                                    className={cn(
                                                        'flex items-start gap-3 rounded-lg border p-3 text-sm',
                                                        checked
                                                            ? 'border-primary/30 bg-primary/5'
                                                            : 'border-border',
                                                        option.disabled
                                                            ? 'opacity-60'
                                                            : 'cursor-pointer',
                                                    )}
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        disabled={option.disabled}
                                                        onCheckedChange={() =>
                                                            toggleSelection('tags', value)
                                                        }
                                                        aria-invalid={
                                                            form.invalid('tags') || undefined
                                                        }
                                                    />
                                                    <div className="flex flex-col gap-1">
                                                        <span className="font-medium">
                                                            {option.label}
                                                        </span>
                                                        {option.disabled ? (
                                                            <span className="text-xs text-muted-foreground">
                                                                Unpublished tags stay selected but cannot
                                                                be newly assigned.
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </ScrollArea>
                                <FieldError>{getArrayFieldError('tags')}</FieldError>
                            </FieldSet>
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
                                    aria-invalid={form.invalid('author_id') || undefined}
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
                                <FieldError>{form.error('author_id')}</FieldError>
                            </Field>

                            {showTemplateField ? (
                                <Field data-invalid={form.invalid('template') || undefined}>
                                    <FieldLabel htmlFor="template">Template</FieldLabel>
                                    <NativeSelect
                                        id="template"
                                        className="w-full"
                                        value={form.data.template}
                                        onChange={(event) =>
                                            form.setField('template', event.target.value)
                                        }
                                        onBlur={() => form.touch('template')}
                                        aria-invalid={
                                            form.invalid('template') || undefined
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
