import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, FileTextIcon, SaveIcon, SendIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { AsteroNote } from '@/components/asteronote/asteronote';
import { hasMeaningfulHtmlContent } from '@/components/asteronote/html-editor-utils';
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
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    EmailOption,
    EmailTemplateEditTarget,
    EmailTemplateFormValues,
} from '@/types/email';

type TemplateFormProps = {
    mode: 'create' | 'edit';
    emailTemplate?: EmailTemplateEditTarget;
    initialValues: EmailTemplateFormValues;
    statusOptions: EmailOption[];
    providerOptions: EmailOption[];
};

export default function TemplateForm({
    mode,
    emailTemplate,
    initialValues,
    statusOptions,
    providerOptions,
}: TemplateFormProps) {
    const form = useAppForm<EmailTemplateFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'email.templates.create.form'
                : `email.templates.edit.${emailTemplate?.id ?? 'new'}`,
        dirtyGuard: { enabled: true },
        rules: {
            name: [formValidators.required('Template name')],
            subject: [formValidators.required('Subject')],
            message: [
                (value, data) => {
                    if (
                        data.is_raw &&
                        typeof value === 'string' &&
                        !hasMeaningfulHtmlContent(value)
                    ) {
                        return 'Message is required.';
                    }

                    return undefined;
                },
                formValidators.required('Message'),
            ],
            provider_id: [formValidators.required('Provider')],
            status: [formValidators.required('Status')],
        },
    });

    const submitUrl =
        mode === 'create'
            ? route('app.masters.email.templates.store')
            : route('app.masters.email.templates.update', emailTemplate!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast:
                mode === 'create'
                    ? 'Email template created.'
                    : 'Email template saved.',
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

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <FileTextIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Template details</CardTitle>
                        </div>
                        <CardDescription>
                            Define the reusable subject line and audience
                            defaults for this email.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <Field
                                data-invalid={form.invalid('name') || undefined}
                            >
                                <FieldLabel htmlFor="name">
                                    Template name <span aria-hidden>*</span>
                                </FieldLabel>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setField(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('name')}
                                    aria-invalid={
                                        form.invalid('name') || undefined
                                    }
                                />
                                <FieldError>{form.error('name')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('subject') || undefined
                                }
                            >
                                <FieldLabel htmlFor="subject">
                                    Subject <span aria-hidden>*</span>
                                </FieldLabel>
                                <Input
                                    id="subject"
                                    value={form.data.subject}
                                    onChange={(event) =>
                                        form.setField(
                                            'subject',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('subject')}
                                    aria-invalid={
                                        form.invalid('subject') || undefined
                                    }
                                />
                                <FieldError>{form.error('subject')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('provider_id') || undefined
                                }
                            >
                                <FieldLabel htmlFor="provider_id">
                                    Delivery provider <span aria-hidden>*</span>
                                </FieldLabel>
                                <NativeSelect
                                    id="provider_id"
                                    name="provider_id"
                                    value={form.data.provider_id}
                                    onChange={(event) =>
                                        form.setField(
                                            'provider_id',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('provider_id')}
                                    aria-invalid={
                                        form.invalid('provider_id') || undefined
                                    }
                                    className="w-full"
                                >
                                    <NativeSelectOption value="">
                                        Select a provider
                                    </NativeSelectOption>
                                    {providerOptions.map((option) => (
                                        <NativeSelectOption
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>
                                    {form.error('provider_id')}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('send_to') || undefined
                                }
                            >
                                <FieldLabel htmlFor="send_to">
                                    Default recipients
                                </FieldLabel>
                                <Textarea
                                    id="send_to"
                                    rows={3}
                                    placeholder="ops@example.com, alerts@example.com"
                                    value={form.data.send_to}
                                    onChange={(event) =>
                                        form.setField(
                                            'send_to',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('send_to')}
                                    aria-invalid={
                                        form.invalid('send_to') || undefined
                                    }
                                />
                                <FieldDescription>
                                    Optional comma-separated email addresses
                                    used as a default recipient list.
                                </FieldDescription>
                                <FieldError>{form.error('send_to')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('status') || undefined
                                }
                            >
                                <FieldSet>
                                    <FieldLegend>Status</FieldLegend>
                                    <FieldDescription>
                                        Inactive templates stay available for
                                        history but are excluded from active
                                        workflows.
                                    </FieldDescription>
                                    <ToggleGroup
                                        type="single"
                                        value={form.data.status}
                                        onValueChange={(value) => {
                                            if (value === '') {
                                                return;
                                            }

                                            form.setField(
                                                'status',
                                                value as EmailTemplateFormValues['status'],
                                            );
                                        }}
                                        variant="outline"
                                        className="w-full flex-wrap"
                                        aria-invalid={
                                            form.invalid('status') || undefined
                                        }
                                    >
                                        {statusOptions.map((option) => (
                                            <ToggleGroupItem
                                                key={option.value}
                                                value={option.value}
                                                className="min-w-[9rem] flex-1"
                                            >
                                                {option.label}
                                            </ToggleGroupItem>
                                        ))}
                                    </ToggleGroup>
                                </FieldSet>
                                <FieldError>{form.error('status')}</FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <SendIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Message body</CardTitle>
                        </div>
                        <CardDescription>
                            Compose the reusable body content that will be sent
                            when this template is triggered.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <Field
                                data-invalid={
                                    form.invalid('message') || undefined
                                }
                            >
                                <FieldLabel htmlFor="message">
                                    Message <span aria-hidden>*</span>
                                </FieldLabel>
                                {form.data.is_raw ? (
                                    <AsteroNote
                                        id="message"
                                        value={form.data.message}
                                        onChange={(value) =>
                                            form.setField('message', value)
                                        }
                                        onBlur={() => form.touch('message')}
                                        invalid={form.invalid('message')}
                                        placeholder="Compose the formatted email body that will be sent to recipients."
                                    />
                                ) : (
                                    <Textarea
                                        id="message"
                                        rows={16}
                                        value={form.data.message}
                                        onChange={(event) =>
                                            form.setField(
                                                'message',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('message')}
                                        aria-invalid={
                                            form.invalid('message') || undefined
                                        }
                                        className="font-mono text-sm"
                                    />
                                )}
                                <FieldDescription>
                                    {form.data.is_raw
                                        ? 'Rich HTML mode uses the built-in formatted editor for email content.'
                                        : 'Plain text mode keeps the legacy source editor for simple template bodies.'}
                                </FieldDescription>
                                <FieldError>{form.error('message')}</FieldError>
                            </Field>

                            <Field orientation="horizontal">
                                <FieldLabel htmlFor="is_raw">
                                    Rich HTML mode
                                </FieldLabel>
                                <FieldDescription>
                                    Enable this to use the formatted editor.
                                    Disable it to edit the raw/plain template
                                    source.
                                </FieldDescription>
                                <Switch
                                    id="is_raw"
                                    checked={form.data.is_raw}
                                    onCheckedChange={(checked) =>
                                        form.setField('is_raw', checked)
                                    }
                                />
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('app.masters.email.templates.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to templates
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner className="size-4" />
                    ) : (
                        <SaveIcon data-icon="inline-start" />
                    )}
                    {mode === 'create' ? 'Create template' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
