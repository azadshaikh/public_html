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
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CmsOption,
    MediaPickerPageProps,
    TagEditDetail,
    TagFormValues,
} from '../../types/cms';

type TagFormProps = {
    mode: 'create' | 'edit';
    initialValues?: TagFormValues;
    statusOptions: CmsOption[];
    metaRobotsOptions: CmsOption[];
    templateOptions: CmsOption[];
    preSlug: string;
    baseUrl: string;
    tag?: TagEditDetail;
} & MediaPickerPageProps;

const emptyValues: TagFormValues = {
    title: '',
    slug: '',
    content: '',
    excerpt: '',
    feature_image: '',
    status: 'draft',
    template: '',
    meta_title: '',
    meta_description: '',
    meta_robots: '',
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

function buildPermalink(
    baseUrl: string,
    preSlug: string,
    slug: string,
): string {
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

export default function TagForm({
    mode,
    initialValues,
    statusOptions,
    metaRobotsOptions,
    templateOptions,
    preSlug,
    baseUrl,
    tag,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
}: TagFormProps) {
    const form = useAppForm<TagFormValues>({
        defaults: initialValues || emptyValues,
        rememberKey:
            mode === 'create'
                ? 'cms.tags.create.form'
                : `cms.tags.edit.${tag?.id}`,
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
        () => buildPermalink(baseUrl, preSlug, form.data.slug),
        [baseUrl, form.data.slug, preSlug],
    );

    const showTemplateField = templateOptions.length > 1;

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('cms.tags.store')
            : route('cms.tags.update', tag!.id);

    const submitLabel = mode === 'create' ? 'Create Tag' : 'Save Changes';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title: mode === 'create' ? 'Tag created' : 'Tag updated',
                description:
                    mode === 'create'
                        ? 'The tag has been created successfully.'
                        : 'The tag has been updated successfully.',
            },
        });
    };

    const handleDelete = () => {
        if (!tag) {
            return;
        }

        if (!window.confirm(`Move "${tag.title}" to trash?`)) {
            return;
        }

        router.delete(route('cms.tags.destroy', tag.id), {
            preserveScroll: true,
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

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Tag content</CardTitle>
                            <CardDescription>
                                Write the description, summary, and SEO metadata
                                for this tag.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field
                                data-invalid={
                                    form.invalid('title') || undefined
                                }
                            >
                                <RequiredLabel htmlFor="title">
                                    Title
                                </RequiredLabel>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setField(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('title')}
                                    aria-invalid={
                                        form.invalid('title') || undefined
                                    }
                                    placeholder="Enter tag title"
                                />
                                <FieldError>{form.error('title')}</FieldError>
                            </Field>

                            <Tabs defaultValue="content">
                                <TabsList variant="line">
                                    <TabsTrigger value="content">
                                        Content
                                    </TabsTrigger>
                                    <TabsTrigger value="seo">SEO</TabsTrigger>
                                </TabsList>

                                <TabsContent
                                    value="content"
                                    className="flex flex-col gap-6"
                                >
                                    <Field
                                        data-invalid={
                                            form.invalid('content') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="content">
                                            Description
                                        </FieldLabel>
                                        <AsteroNote
                                            id="content"
                                            value={form.data.content}
                                            onChange={(value) =>
                                                form.setField('content', value)
                                            }
                                            onBlur={() => form.touch('content')}
                                            placeholder="Write the tag description"
                                            invalid={
                                                form.invalid('content') ||
                                                undefined
                                            }
                                        />
                                        <FieldError>
                                            {form.error('content')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('excerpt') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="excerpt">
                                            Excerpt
                                        </FieldLabel>
                                        <Textarea
                                            id="excerpt"
                                            rows={4}
                                            value={form.data.excerpt}
                                            onChange={(event) =>
                                                form.setField(
                                                    'excerpt',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() => form.touch('excerpt')}
                                            aria-invalid={
                                                form.invalid('excerpt') ||
                                                undefined
                                            }
                                            placeholder="Enter a short excerpt"
                                        />
                                        <FieldDescription>
                                            Used in listings, previews, and
                                            search snippets.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('excerpt')}
                                        </FieldError>
                                    </Field>

                                    {tag ? (
                                        <div className="text-sm text-muted-foreground">
                                            Last updated{' '}
                                            {tag.updated_at_human ?? 'recently'}
                                            {tag.updated_at_formatted
                                                ? ` (${tag.updated_at_formatted})`
                                                : ''}
                                        </div>
                                    ) : null}
                                </TabsContent>

                                <TabsContent
                                    value="seo"
                                    className="flex flex-col gap-6"
                                >
                                    <Field
                                        data-invalid={
                                            form.invalid('meta_title') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="meta_title">
                                            Meta title
                                        </FieldLabel>
                                        <Input
                                            id="meta_title"
                                            value={form.data.meta_title}
                                            onChange={(event) =>
                                                form.setField(
                                                    'meta_title',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('meta_title')
                                            }
                                            aria-invalid={
                                                form.invalid('meta_title') ||
                                                undefined
                                            }
                                            placeholder="Enter meta title"
                                        />
                                        <FieldDescription>
                                            Recommended length: 50–60
                                            characters.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('meta_title')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('meta_description') ||
                                            undefined
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
                                                form.invalid(
                                                    'meta_description',
                                                ) || undefined
                                            }
                                            placeholder="Enter meta description"
                                        />
                                        <FieldError>
                                            {form.error('meta_description')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('meta_robots') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="meta_robots">
                                            Meta robots
                                        </FieldLabel>
                                        <NativeSelect
                                            id="meta_robots"
                                            className="w-full"
                                            value={form.data.meta_robots}
                                            onChange={(event) =>
                                                form.setField(
                                                    'meta_robots',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('meta_robots')
                                            }
                                            aria-invalid={
                                                form.invalid('meta_robots') ||
                                                undefined
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
                                        <FieldError>
                                            {form.error('meta_robots')}
                                        </FieldError>
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
                                Choose an image or upload a new one.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <MediaPickerField
                                value={form.data.feature_image || null}
                                previewUrl={tag?.featured_image_url}
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
                                pickerStatistics={pickerStatistics}
                                pickerAction={
                                    mode === 'create'
                                        ? route('cms.tags.create')
                                        : route('cms.tags.edit', tag!.id)
                                }
                            />
                            <FieldError>
                                {form.error('feature_image')}
                            </FieldError>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2Icon className="size-4 text-muted-foreground" />
                                <CardTitle>Settings</CardTitle>
                            </div>
                            <CardDescription>
                                Control publishing for this tag.
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
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ShieldIcon className="size-4 text-muted-foreground" />
                                <CardTitle>More options</CardTitle>
                            </div>
                            <CardDescription>
                                Fine-tune the permalink and template.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field
                                data-invalid={form.invalid('slug') || undefined}
                            >
                                <FieldLabel htmlFor="slug">
                                    Permalink
                                </FieldLabel>
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
                                            form.setField(
                                                'slug',
                                                slugify(event.target.value),
                                            );
                                        }}
                                        onBlur={() => form.touch('slug')}
                                        aria-invalid={
                                            form.invalid('slug') || undefined
                                        }
                                        placeholder="auto-generated-from-title"
                                    />
                                </div>
                                {tag?.permalink_url ? (
                                    <a
                                        href={permalinkPreview}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                    >
                                        <span className="truncate">
                                            {permalinkPreview}
                                        </span>
                                        <ExternalLinkIcon className="size-3.5 shrink-0" />
                                    </a>
                                ) : (
                                    <FieldDescription>
                                        {permalinkPreview}
                                    </FieldDescription>
                                )}
                                <FieldError>{form.error('slug')}</FieldError>
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
                                        for this tag.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('template')}
                                    </FieldError>
                                </Field>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{submitLabel}</CardTitle>
                            <CardDescription>
                                Save this tag and return to the editor.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter className="flex-col gap-3">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={form.processing}
                            >
                                {form.processing ? (
                                    <Spinner className="size-4" />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                {submitLabel}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                asChild
                            >
                                <Link href={route('cms.tags.index')}>
                                    Back to Tags
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>

                    {mode === 'edit' && tag ? (
                        <Card className="border-destructive/30">
                            <CardHeader>
                                <CardTitle>Danger zone</CardTitle>
                                <CardDescription>
                                    Move this tag to trash. You can restore it
                                    later from the trash tab.
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
