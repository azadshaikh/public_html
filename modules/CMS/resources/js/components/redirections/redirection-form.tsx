'use client';

import { Link, router } from '@inertiajs/react';
import {
    ArrowRightLeftIcon,
    Clock3Icon,
    ExternalLinkIcon,
    InfoIcon,
    SaveIcon,
    Trash2Icon,
} from 'lucide-react';
import { useMemo } from 'react';
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
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CmsOption,
    RedirectionEditDetail,
    RedirectionFormValues,
} from '../../types/cms';

type RedirectionFormProps = {
    mode: 'create' | 'edit';
    initialValues?: RedirectionFormValues;
    statusOptions: CmsOption[];
    redirectTypeOptions: CmsOption[];
    urlTypeOptions: CmsOption[];
    matchTypeOptions: CmsOption[];
    baseUrl: string;
    redirection?: RedirectionEditDetail;
};

const emptyValues: RedirectionFormValues = {
    source_url: '',
    target_url: '',
    redirect_type: '301',
    url_type: 'internal',
    match_type: 'exact',
    status: 'active',
    notes: '',
    expires_at: '',
};

const matchHelpText: Record<string, string> = {
    exact: 'URL must match exactly, such as /old-page redirecting only /old-page.',
    wildcard:
        'Use * for a single segment and ** for multiple segments, such as /blog/*.',
    regex: 'Use a regular expression without delimiters. Captured groups can be reused in the target as $1, $2, and so on.',
};

const redirectTypeHelpText: Record<string, string> = {
    '301': 'Permanent redirect. Search engines transfer ranking signals to the new URL.',
    '302': 'Temporary redirect. Search engines keep the source URL indexed.',
    '307': 'Temporary redirect that preserves the HTTP method.',
    '308': 'Permanent redirect that preserves the HTTP method.',
};

function normalizeInternalPath(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '' || trimmed.startsWith('/')) {
        return trimmed;
    }

    return `/${trimmed}`;
}

function buildPreviewUrl(
    baseUrl: string,
    sourceUrl: string,
    matchType: string,
): string | null {
    if (matchType !== 'exact') {
        return null;
    }

    const normalizedSourceUrl = normalizeInternalPath(sourceUrl);

    if (normalizedSourceUrl === '') {
        return null;
    }

    return `${baseUrl}${normalizedSourceUrl}`;
}

export default function RedirectionForm({
    mode,
    initialValues,
    statusOptions,
    redirectTypeOptions,
    urlTypeOptions,
    matchTypeOptions,
    baseUrl,
    redirection,
}: RedirectionFormProps) {
    const form = useAppForm<RedirectionFormValues>({
        defaults: initialValues ?? emptyValues,
        rememberKey:
            mode === 'create'
                ? 'cms.redirections.create.form'
                : `cms.redirections.edit.${redirection?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            source_url: [
                formValidators.required('Source URL'),
                (value, data) => {
                    if (data.match_type === 'regex' || value.trim() === '') {
                        return undefined;
                    }

                    return value.trim().startsWith('/')
                        ? undefined
                        : 'Source URL must start with /.';
                },
            ],
            target_url: [
                formValidators.required('Target URL'),
                (value, data) => {
                    if (value.trim() === '') {
                        return undefined;
                    }

                    if (
                        data.url_type === 'internal' &&
                        !value.trim().startsWith('/')
                    ) {
                        return 'Internal target URLs must start with /.';
                    }

                    if (
                        data.url_type === 'external' &&
                        !/^https?:\/\//i.test(value.trim())
                    ) {
                        return 'External target URLs must start with http:// or https://.';
                    }

                    return undefined;
                },
            ],
            redirect_type: [formValidators.required('HTTP status code')],
            url_type: [formValidators.required('Target type')],
            match_type: [formValidators.required('Matching type')],
            status: [formValidators.required('Status')],
        },
    });

    const selectedMatchType = useMemo(
        () =>
            matchTypeOptions.find(
                (option) => String(option.value) === form.data.match_type,
            ),
        [form.data.match_type, matchTypeOptions],
    );

    const selectedUrlType = useMemo(
        () =>
            urlTypeOptions.find(
                (option) => String(option.value) === form.data.url_type,
            ),
        [form.data.url_type, urlTypeOptions],
    );

    const selectedRedirectType = useMemo(
        () =>
            redirectTypeOptions.find(
                (option) => String(option.value) === form.data.redirect_type,
            ),
        [form.data.redirect_type, redirectTypeOptions],
    );

    const previewUrl = useMemo(
        () =>
            mode === 'edit'
                ? (redirection?.preview_url ?? null)
                : buildPreviewUrl(
                      baseUrl,
                      form.data.source_url,
                      form.data.match_type,
                  ),
        [
            baseUrl,
            form.data.match_type,
            form.data.source_url,
            mode,
            redirection,
        ],
    );

    const sourcePlaceholder =
        form.data.match_type === 'regex'
            ? '^/old-(.+)$'
            : form.data.match_type === 'wildcard'
              ? '/blog/*'
              : '/old-path';

    const targetPlaceholder =
        form.data.url_type === 'external'
            ? 'https://example.com/new-page'
            : form.data.match_type === 'exact'
              ? '/new-path'
              : '/articles/$1';

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('cms.redirections.store')
            : route('cms.redirections.update', redirection!.id);

    const submitLabel =
        mode === 'create' ? 'Create redirection' : 'Save redirection';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title:
                    mode === 'create'
                        ? 'Redirection created'
                        : 'Redirection updated',
                description:
                    mode === 'create'
                        ? 'The redirect rule has been created successfully.'
                        : 'The redirect rule has been updated successfully.',
            },
        });
    };

    const handleDelete = () => {
        if (!redirection) {
            return;
        }

        if (!window.confirm('Move this redirection to trash?')) {
            return;
        }

        router.delete(route('cms.redirections.destroy', redirection.id));
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
                <div className="flex min-w-0 flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Redirect rule</CardTitle>
                            <CardDescription>
                                Configure how the source path is matched and
                                where visitors should be sent next.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('match_type') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="match_type">
                                        Matching type{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="match_type"
                                        value={form.data.match_type}
                                        onChange={(event) =>
                                            form.setField(
                                                'match_type',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('match_type')}
                                        aria-invalid={
                                            form.invalid('match_type') ||
                                            undefined
                                        }
                                    >
                                        {matchTypeOptions.map((option) => (
                                            <NativeSelectOption
                                                key={String(option.value)}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldDescription>
                                        {selectedMatchType?.description ??
                                            matchHelpText[form.data.match_type]}
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('match_type')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('source_url') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="source_url">
                                        Source URL{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="source_url"
                                        value={form.data.source_url}
                                        onChange={(event) =>
                                            form.setField(
                                                'source_url',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => {
                                            if (
                                                form.data.match_type !== 'regex'
                                            ) {
                                                form.setField(
                                                    'source_url',
                                                    normalizeInternalPath(
                                                        form.data.source_url,
                                                    ),
                                                );
                                            }

                                            form.touch('source_url');
                                        }}
                                        aria-invalid={
                                            form.invalid('source_url') ||
                                            undefined
                                        }
                                        placeholder={sourcePlaceholder}
                                        className="font-mono"
                                    />
                                    <FieldDescription>
                                        {form.data.match_type === 'regex'
                                            ? 'Regex patterns are evaluated against the request path only.'
                                            : `Requests are matched against paths on ${baseUrl}.`}
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('source_url')}
                                    </FieldError>
                                </Field>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <Field
                                        data-invalid={
                                            form.invalid('url_type') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="url_type">
                                            Target type{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <NativeSelect
                                            id="url_type"
                                            value={form.data.url_type}
                                            onChange={(event) =>
                                                form.setField(
                                                    'url_type',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('url_type')
                                            }
                                            aria-invalid={
                                                form.invalid('url_type') ||
                                                undefined
                                            }
                                        >
                                            {urlTypeOptions.map((option) => (
                                                <NativeSelectOption
                                                    key={String(option.value)}
                                                    value={String(option.value)}
                                                >
                                                    {option.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                        <FieldDescription>
                                            {selectedUrlType?.description ??
                                                'Choose whether the destination stays on this site or leaves it.'}
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('url_type')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('redirect_type') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="redirect_type">
                                            HTTP status code{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <NativeSelect
                                            id="redirect_type"
                                            value={form.data.redirect_type}
                                            onChange={(event) =>
                                                form.setField(
                                                    'redirect_type',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('redirect_type')
                                            }
                                            aria-invalid={
                                                form.invalid('redirect_type') ||
                                                undefined
                                            }
                                        >
                                            {redirectTypeOptions.map(
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
                                        <FieldDescription>
                                            {selectedRedirectType?.description ??
                                                redirectTypeHelpText[
                                                    form.data.redirect_type
                                                ]}
                                        </FieldDescription>
                                        <FieldError>
                                            {form.error('redirect_type')}
                                        </FieldError>
                                    </Field>
                                </div>

                                <Field
                                    data-invalid={
                                        form.invalid('target_url') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="target_url">
                                        Target URL{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="target_url"
                                        value={form.data.target_url}
                                        onChange={(event) =>
                                            form.setField(
                                                'target_url',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => {
                                            if (
                                                form.data.url_type ===
                                                'internal'
                                            ) {
                                                form.setField(
                                                    'target_url',
                                                    normalizeInternalPath(
                                                        form.data.target_url,
                                                    ),
                                                );
                                            }

                                            form.touch('target_url');
                                        }}
                                        aria-invalid={
                                            form.invalid('target_url') ||
                                            undefined
                                        }
                                        placeholder={targetPlaceholder}
                                        className="font-mono"
                                    />
                                    <FieldDescription>
                                        {form.data.url_type === 'external'
                                            ? 'Use a full URL starting with https:// for external destinations.'
                                            : form.data.match_type === 'exact'
                                              ? 'Use an internal path such as /new-page.'
                                              : 'Captured groups from the match can be reused as $1, $2, and so on.'}
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('target_url')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Alert>
                        <InfoIcon />
                        <AlertTitle>Pattern guidance</AlertTitle>
                        <AlertDescription>
                            {matchHelpText[form.data.match_type]}
                        </AlertDescription>
                    </Alert>

                    <Card>
                        <CardHeader>
                            <CardTitle>Examples</CardTitle>
                            <CardDescription>
                                Common redirect patterns for internal and
                                external destinations.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-3">
                            <div className="rounded-xl border bg-muted/30 p-4">
                                <p className="text-sm font-medium">Exact</p>
                                <p className="mt-2 font-mono text-sm text-muted-foreground">
                                    /old-page → /new-page
                                </p>
                            </div>
                            <div className="rounded-xl border bg-muted/30 p-4">
                                <p className="text-sm font-medium">Wildcard</p>
                                <p className="mt-2 font-mono text-sm text-muted-foreground">
                                    /blog/* → /articles/$1
                                </p>
                            </div>
                            <div className="rounded-xl border bg-muted/30 p-4">
                                <p className="text-sm font-medium">External</p>
                                <p className="mt-2 font-mono text-sm text-muted-foreground">
                                    /pricing → https://example.com
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Publishing</CardTitle>
                            <CardDescription>
                                Control availability, expiration, and internal
                                notes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <Field
                                data-invalid={
                                    form.invalid('status') || undefined
                                }
                            >
                                <FieldLabel htmlFor="status">
                                    Status{' '}
                                    <span className="text-destructive">*</span>
                                </FieldLabel>
                                <NativeSelect
                                    id="status"
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
                                <FieldError>{form.error('status')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('expires_at') || undefined
                                }
                            >
                                <FieldLabel htmlFor="expires_at">
                                    Expires at
                                </FieldLabel>
                                <Input
                                    id="expires_at"
                                    type="datetime-local"
                                    value={form.data.expires_at}
                                    onChange={(event) =>
                                        form.setField(
                                            'expires_at',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('expires_at')}
                                    aria-invalid={
                                        form.invalid('expires_at') || undefined
                                    }
                                />
                                <FieldDescription>
                                    Leave empty to keep the redirect active
                                    until you disable or remove it.
                                </FieldDescription>
                                <FieldError>
                                    {form.error('expires_at')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('notes') || undefined
                                }
                            >
                                <FieldLabel htmlFor="notes">
                                    Internal notes
                                </FieldLabel>
                                <Textarea
                                    id="notes"
                                    rows={5}
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setField(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('notes')}
                                    aria-invalid={
                                        form.invalid('notes') || undefined
                                    }
                                    placeholder="Share context with your team about why this redirect exists."
                                />
                                <FieldDescription>
                                    Private context for editors and
                                    administrators.
                                </FieldDescription>
                                <FieldError>{form.error('notes')}</FieldError>
                            </Field>

                            {mode === 'edit' && redirection ? (
                                <div className="grid gap-3 rounded-xl border bg-muted/20 p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-sm text-muted-foreground">
                                            Hits
                                        </span>
                                        <Badge variant="secondary">
                                            {redirection.hits.toLocaleString()}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-sm text-muted-foreground">
                                            Last hit
                                        </span>
                                        <span className="text-right text-sm">
                                            {redirection.last_hit_at_human ??
                                                'Never'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-sm text-muted-foreground">
                                            Created
                                        </span>
                                        <span className="text-right text-sm">
                                            {redirection.created_at_human ??
                                                'Recently'}
                                        </span>
                                    </div>
                                </div>
                            ) : null}
                        </CardContent>
                        <CardFooter className="flex flex-col items-stretch gap-3">
                            {previewUrl ? (
                                <Button variant="outline" asChild>
                                    <a
                                        href={previewUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLinkIcon data-icon="inline-start" />
                                        Open source URL
                                    </a>
                                </Button>
                            ) : null}

                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <SaveIcon data-icon="inline-start" />
                                    )}
                                    {submitLabel}
                                </Button>
                                <Button variant="outline" type="button" asChild>
                                    <Link
                                        href={route('cms.redirections.index')}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                            </div>

                            {mode === 'edit' && redirection ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={handleDelete}
                                >
                                    <Trash2Icon data-icon="inline-start" />
                                    Move to trash
                                </Button>
                            ) : null}
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Quick checklist</CardTitle>
                            <CardDescription>
                                Keep redirect rules predictable and easy to
                                maintain.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm text-muted-foreground">
                            <div className="flex items-start gap-3">
                                <ArrowRightLeftIcon className="mt-0.5 size-4 text-foreground/70" />
                                <p>
                                    Prefer <strong>301</strong> for permanent
                                    URL changes and <strong>302</strong> only
                                    for short-lived moves.
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <Clock3Icon className="mt-0.5 size-4 text-foreground/70" />
                                <p>
                                    Add an expiration when the redirect supports
                                    a campaign, migration step, or temporary
                                    promotion.
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <InfoIcon className="mt-0.5 size-4 text-foreground/70" />
                                <p>
                                    Document complex regex or wildcard rules so
                                    another editor can safely update them later.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}
