'use client';

import { Link, router } from '@inertiajs/react';
import {
    ActivityIcon,
    ArrowUpRightIcon,
    FileCode2Icon,
    InfoIcon,
    MessageSquareQuoteIcon,
    SaveIcon,
    Settings2Icon,
    Trash2Icon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CmsOption,
    FormEditDetail,
    FormFormValues,
} from '../../types/cms';

type CmsFormProps = {
    mode: 'create' | 'edit';
    initialValues?: FormFormValues;
    statusOptions: CmsOption[];
    templateOptions: CmsOption[];
    formTypeOptions: CmsOption[];
    form?: FormEditDetail;
};

const emptyValues: FormFormValues = {
    title: '',
    slug: '',
    shortcode: '',
    status: 'draft',
    template: 'default',
    form_type: 'standard',
    html: '',
    css: '',
    store_in_database: true,
    confirmation_type: 'message',
    confirmation_message: 'Thanks for contacting us. We will get back to you soon.',
    redirect_url: '',
    is_active: true,
    published_at: '',
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

function buildShortcode(value: string): string {
    const slug = slugify(value).replace(/-/g, '_');

    return slug === '' ? '' : `form_${slug}`;
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

export default function CmsForm({
    mode,
    initialValues,
    statusOptions,
    templateOptions,
    formTypeOptions,
    form: currentForm,
}: CmsFormProps) {
    const form = useAppForm<FormFormValues>({
        defaults: initialValues ?? emptyValues,
        rememberKey:
            mode === 'create'
                ? 'cms.forms.create.form'
                : `cms.forms.edit.${currentForm?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            title: [formValidators.required('Title')],
            html: [formValidators.required('Form HTML')],
            status: [formValidators.required('Status')],
            confirmation_message: [
                (value, data) => {
                    if (data.confirmation_type !== 'message') {
                        return undefined;
                    }

                    return value.trim() === ''
                        ? 'Confirmation message is required.'
                        : undefined;
                },
            ],
            redirect_url: [
                (value, data) => {
                    if (data.confirmation_type !== 'redirect') {
                        return undefined;
                    }

                    if (value.trim() === '') {
                        return 'Redirect URL is required.';
                    }

                    return /^https?:\/\//i.test(value.trim())
                        ? undefined
                        : 'Redirect URL must start with http:// or https://.';
                },
            ],
        },
    });

    const [slugTouched, setSlugTouched] = useState(mode === 'edit');
    const [shortcodeTouched, setShortcodeTouched] = useState(mode === 'edit');

    useEffect(() => {
        if (mode !== 'create' || slugTouched) {
            return;
        }

        const nextSlug = slugify(form.data.title);

        if (nextSlug !== form.data.slug) {
            form.setField('slug', nextSlug);
        }
    }, [form, mode, slugTouched]);

    useEffect(() => {
        if (mode !== 'create' || shortcodeTouched) {
            return;
        }

        const nextShortcode = buildShortcode(form.data.title);

        if (nextShortcode !== form.data.shortcode) {
            form.setField('shortcode', nextShortcode);
        }
    }, [form, mode, shortcodeTouched]);

    const selectedTemplate = useMemo(
        () =>
            templateOptions.find(
                (option) => String(option.value) === form.data.template,
            ),
        [form.data.template, templateOptions],
    );

    const selectedFormType = useMemo(
        () =>
            formTypeOptions.find(
                (option) => String(option.value) === form.data.form_type,
            ),
        [form.data.form_type, formTypeOptions],
    );

    const selectedStatus = useMemo(
        () =>
            statusOptions.find(
                (option) => String(option.value) === form.data.status,
            ),
        [form.data.status, statusOptions],
    );

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('cms.form.store')
            : route('cms.form.update', currentForm!.id);

    const submitLabel = mode === 'create' ? 'Create form' : 'Save changes';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title: mode === 'create' ? 'Form created' : 'Form updated',
                description:
                    mode === 'create'
                        ? 'The form has been created successfully.'
                        : 'The form has been updated successfully.',
            },
        });
    };

    const handleDelete = () => {
        if (!currentForm) {
            return;
        }

        if (!window.confirm(`Move "${currentForm.title}" to trash?`)) {
            return;
        }

        router.delete(route('cms.form.destroy', currentForm.id), {
            preserveScroll: true,
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex min-w-0 flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <FileCode2Icon className="size-4 text-muted-foreground" />
                                <CardTitle>Form builder</CardTitle>
                            </div>
                            <CardDescription>
                                Configure the public details, markup, and confirmation behavior for
                                this form.
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
                                    placeholder="Contact us form"
                                />
                                <FieldError>{form.error('title')}</FieldError>
                            </Field>

                            <FieldGroup>
                                <Field data-invalid={form.invalid('slug') || undefined}>
                                    <FieldLabel htmlFor="slug">Slug</FieldLabel>
                                    <Input
                                        id="slug"
                                        value={form.data.slug}
                                        onChange={(event) => {
                                            setSlugTouched(true);
                                            form.setField(
                                                'slug',
                                                slugify(event.target.value),
                                            );
                                        }}
                                        onBlur={() => form.touch('slug')}
                                        aria-invalid={form.invalid('slug') || undefined}
                                        placeholder="contact-us-form"
                                    />
                                    <FieldDescription>
                                        Used for URLs and internal references.
                                    </FieldDescription>
                                    <FieldError>{form.error('slug')}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('shortcode') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="shortcode">Shortcode</FieldLabel>
                                    <Input
                                        id="shortcode"
                                        value={form.data.shortcode}
                                        onChange={(event) => {
                                            setShortcodeTouched(true);
                                            form.setField(
                                                'shortcode',
                                                event.target.value
                                                    .toLowerCase()
                                                    .replace(/[^a-z0-9_]/g, '_')
                                                    .replace(/_+/g, '_')
                                                    .replace(/^_+/, '')
                                                    .replace(/_+$/, ''),
                                            );
                                        }}
                                        onBlur={() => form.touch('shortcode')}
                                        aria-invalid={
                                            form.invalid('shortcode') || undefined
                                        }
                                        placeholder="form_contact_us"
                                    />
                                    <FieldDescription>
                                        Embed this form using{' '}
                                        <span className="font-mono text-xs">
                                            [{form.data.shortcode || 'form_shortcode'}]
                                        </span>
                                        .
                                    </FieldDescription>
                                    <FieldError>{form.error('shortcode')}</FieldError>
                                </Field>
                            </FieldGroup>

                            <Field data-invalid={form.invalid('html') || undefined}>
                                <RequiredLabel htmlFor="html">Form HTML</RequiredLabel>
                                <Textarea
                                    id="html"
                                    rows={14}
                                    value={form.data.html}
                                    onChange={(event) =>
                                        form.setField('html', event.target.value)
                                    }
                                    onBlur={() => form.touch('html')}
                                    aria-invalid={form.invalid('html') || undefined}
                                    placeholder="<form>...your fields...</form>"
                                    className="font-mono text-sm"
                                />
                                <FieldDescription>
                                    Paste the rendered markup for the form fields and structure.
                                </FieldDescription>
                                <FieldError>{form.error('html')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('css') || undefined}>
                                <FieldLabel htmlFor="css">Custom CSS</FieldLabel>
                                <Textarea
                                    id="css"
                                    rows={10}
                                    value={form.data.css}
                                    onChange={(event) =>
                                        form.setField('css', event.target.value)
                                    }
                                    onBlur={() => form.touch('css')}
                                    aria-invalid={form.invalid('css') || undefined}
                                    placeholder=".form-shell { display: grid; gap: 1rem; }"
                                    className="font-mono text-sm"
                                />
                                <FieldDescription>
                                    Optional styles that apply only to this form.
                                </FieldDescription>
                                <FieldError>{form.error('css')}</FieldError>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <MessageSquareQuoteIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Confirmation flow</CardTitle>
                            </div>
                            <CardDescription>
                                Choose what happens after a successful submission.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field>
                                <RequiredLabel htmlFor="confirmation_type">
                                    Confirmation type
                                </RequiredLabel>
                                <NativeSelect
                                    id="confirmation_type"
                                    className="w-full"
                                    value={form.data.confirmation_type}
                                    onChange={(event) =>
                                        form.setField(
                                            'confirmation_type',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('confirmation_type')}
                                >
                                    <NativeSelectOption value="message">
                                        Success message
                                    </NativeSelectOption>
                                    <NativeSelectOption value="redirect">
                                        Redirect to URL
                                    </NativeSelectOption>
                                </NativeSelect>
                                <FieldDescription>
                                    Use a thank-you message for standard flows or redirect to a
                                    custom landing page.
                                </FieldDescription>
                            </Field>

                            {form.data.confirmation_type === 'message' ? (
                                <Field
                                    data-invalid={
                                        form.invalid('confirmation_message') || undefined
                                    }
                                >
                                    <RequiredLabel htmlFor="confirmation_message">
                                        Confirmation message
                                    </RequiredLabel>
                                    <Textarea
                                        id="confirmation_message"
                                        rows={4}
                                        value={form.data.confirmation_message}
                                        onChange={(event) =>
                                            form.setField(
                                                'confirmation_message',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('confirmation_message')
                                        }
                                        aria-invalid={
                                            form.invalid('confirmation_message') ||
                                            undefined
                                        }
                                        placeholder="Thanks for contacting us. We will reply shortly."
                                    />
                                    <FieldError>
                                        {form.error('confirmation_message')}
                                    </FieldError>
                                </Field>
                            ) : (
                                <Field
                                    data-invalid={
                                        form.invalid('redirect_url') || undefined
                                    }
                                >
                                    <RequiredLabel htmlFor="redirect_url">
                                        Redirect URL
                                    </RequiredLabel>
                                    <Input
                                        id="redirect_url"
                                        value={form.data.redirect_url}
                                        onChange={(event) =>
                                            form.setField('redirect_url', event.target.value)
                                        }
                                        onBlur={() => form.touch('redirect_url')}
                                        aria-invalid={
                                            form.invalid('redirect_url') || undefined
                                        }
                                        placeholder="https://example.com/thank-you"
                                    />
                                    <FieldDescription>
                                        Send visitors to a confirmation or booking page after they
                                        submit the form.
                                    </FieldDescription>
                                    <FieldError>{form.error('redirect_url')}</FieldError>
                                </Field>
                            )}

                            <Alert>
                                <InfoIcon className="size-4" />
                                <AlertTitle>Submission storage</AlertTitle>
                                <AlertDescription>
                                    Turn off database storage only if the form is handled entirely
                                    by a third-party integration.
                                </AlertDescription>
                            </Alert>

                            <Field orientation="horizontal">
                                <Switch
                                    checked={form.data.store_in_database}
                                    onCheckedChange={(checked) =>
                                        form.setField('store_in_database', checked)
                                    }
                                />
                                <div className="flex flex-col gap-1">
                                    <FieldLabel>Store submissions in database</FieldLabel>
                                    <FieldDescription>
                                        Keep entries available in the CMS for reporting and follow-up.
                                    </FieldDescription>
                                </div>
                            </Field>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2Icon className="size-4 text-muted-foreground" />
                                <CardTitle>Publishing</CardTitle>
                            </div>
                            <CardDescription>
                                Control availability, presentation, and publication timing.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
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
                                    aria-invalid={form.invalid('template') || undefined}
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
                                {selectedTemplate?.description ? (
                                    <FieldDescription>
                                        {selectedTemplate.description}
                                    </FieldDescription>
                                ) : null}
                                <FieldError>{form.error('template')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('form_type') || undefined}>
                                <FieldLabel htmlFor="form_type">Form type</FieldLabel>
                                <NativeSelect
                                    id="form_type"
                                    className="w-full"
                                    value={form.data.form_type}
                                    onChange={(event) =>
                                        form.setField('form_type', event.target.value)
                                    }
                                    onBlur={() => form.touch('form_type')}
                                    aria-invalid={form.invalid('form_type') || undefined}
                                >
                                    {formTypeOptions.map((option) => (
                                        <NativeSelectOption
                                            key={String(option.value)}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                {selectedFormType?.description ? (
                                    <FieldDescription>
                                        {selectedFormType.description}
                                    </FieldDescription>
                                ) : null}
                                <FieldError>{form.error('form_type')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('status') || undefined}>
                                <RequiredLabel htmlFor="status">Status</RequiredLabel>
                                <NativeSelect
                                    id="status"
                                    className="w-full"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setField('status', event.target.value)
                                    }
                                    onBlur={() => form.touch('status')}
                                    aria-invalid={form.invalid('status') || undefined}
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
                                {selectedStatus?.description ? (
                                    <FieldDescription>
                                        {selectedStatus.description}
                                    </FieldDescription>
                                ) : null}
                                <FieldError>{form.error('status')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('published_at') || undefined
                                }
                            >
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
                                    Leave empty to publish immediately when the status is set to
                                    published.
                                </FieldDescription>
                                <FieldError>{form.error('published_at')}</FieldError>
                            </Field>

                            <Field orientation="horizontal">
                                <Switch
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setField('is_active', checked)
                                    }
                                />
                                <div className="flex flex-col gap-1">
                                    <FieldLabel>Accept new submissions</FieldLabel>
                                    <FieldDescription>
                                        Inactive forms stay saved but stop accepting new entries.
                                    </FieldDescription>
                                </div>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ActivityIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Summary</CardTitle>
                            </div>
                            <CardDescription>
                                Quick details for publishing, tracking, and embedding.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 text-sm">
                            {currentForm ? (
                                <>
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">Submissions</span>
                                        <span className="font-medium tabular-nums">
                                            {currentForm.submissions_count}
                                        </span>
                                    </div>
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">Views</span>
                                        <span className="font-medium tabular-nums">
                                            {currentForm.views_count}
                                        </span>
                                    </div>
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">Conversion</span>
                                        <span className="font-medium">
                                            {currentForm.conversion_rate_display}
                                        </span>
                                    </div>
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">Published</span>
                                        <span className="text-right font-medium">
                                            {currentForm.published_at_formatted ?? 'Not scheduled'}
                                        </span>
                                    </div>
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">Updated</span>
                                        <span className="text-right font-medium">
                                            {currentForm.updated_at_formatted ?? 'Just now'}
                                        </span>
                                    </div>
                                </>
                            ) : (
                                <Alert>
                                    <ArrowUpRightIcon className="size-4" />
                                    <AlertTitle>Ready to publish</AlertTitle>
                                    <AlertDescription>
                                        Create the form first, then return here to review submission
                                        performance and publication history.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="rounded-lg border bg-muted/20 p-4">
                                <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Embed shortcode
                                </div>
                                <div className="mt-2 font-mono text-sm text-foreground">
                                    [{form.data.shortcode || 'form_shortcode'}]
                                </div>
                            </div>

                            {currentForm?.updated_at_human ? (
                                <p className="text-xs text-muted-foreground">
                                    Last updated {currentForm.updated_at_human}.
                                </p>
                            ) : null}
                        </CardContent>
                        <CardFooter className="flex flex-col gap-3">
                            <Button type="submit" className="w-full" disabled={form.processing}>
                                {form.processing ? (
                                    <Spinner className="mr-2 size-4" />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                {submitLabel}
                            </Button>

                            <Button variant="outline" className="w-full" asChild>
                                <Link href={route('cms.form.index')}>Back to Forms</Link>
                            </Button>

                            {currentForm ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    className="w-full"
                                    onClick={handleDelete}
                                >
                                    <Trash2Icon data-icon="inline-start" />
                                    Move to Trash
                                </Button>
                            ) : null}
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </form>
    );
}
