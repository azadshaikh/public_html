import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, MailIcon, SaveIcon, ServerIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import PasswordInput from '@/components/password-input';
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
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    EmailOption,
    EmailProviderEditTarget,
    EmailProviderFormValues,
} from '@/types/email';

type ProviderFormProps = {
    mode: 'create' | 'edit';
    emailProvider?: EmailProviderEditTarget;
    initialValues: EmailProviderFormValues;
    statusOptions: EmailOption[];
    encryptionOptions: EmailOption[];
};

export default function ProviderForm({
    mode,
    emailProvider,
    initialValues,
    statusOptions,
    encryptionOptions,
}: ProviderFormProps) {
    const form = useAppForm<EmailProviderFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'email.providers.create.form'
                : `email.providers.edit.${emailProvider?.id ?? 'new'}`,
        dirtyGuard: { enabled: true },
        rules: {
            name: [formValidators.required('Provider name')],
            sender_name: [formValidators.required('Sender name')],
            sender_email: [formValidators.required('Sender email')],
            smtp_host: [formValidators.required('SMTP host')],
            smtp_user: [formValidators.required('SMTP username')],
            smtp_password:
                mode === 'create'
                    ? [formValidators.required('SMTP password')]
                    : [],
            smtp_port: [formValidators.required('SMTP port')],
            smtp_encryption: [formValidators.required('Encryption')],
            status: [formValidators.required('Status')],
        },
    });

    const submitUrl =
        mode === 'create'
            ? route('app.masters.email.providers.store')
            : route('app.masters.email.providers.update', emailProvider!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast:
                mode === 'create'
                    ? 'Email provider created.'
                    : 'Email provider saved.',
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <MailIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Provider identity</CardTitle>
                        </div>
                        <CardDescription>
                            Configure the mailbox identity this provider will send from.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <Field data-invalid={form.invalid('name') || undefined}>
                                <FieldLabel htmlFor="name">
                                    Provider name <span aria-hidden>*</span>
                                </FieldLabel>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setField('name', event.target.value)
                                    }
                                    onBlur={() => form.touch('name')}
                                    aria-invalid={form.invalid('name') || undefined}
                                />
                                <FieldError>{form.error('name')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('description') || undefined}>
                                <FieldLabel htmlFor="description">Description</FieldLabel>
                                <Textarea
                                    id="description"
                                    rows={4}
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setField(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.touch('description')}
                                    aria-invalid={form.invalid('description') || undefined}
                                />
                                <FieldError>{form.error('description')}</FieldError>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={form.invalid('sender_name') || undefined}>
                                    <FieldLabel htmlFor="sender_name">
                                        Sender name <span aria-hidden>*</span>
                                    </FieldLabel>
                                    <Input
                                        id="sender_name"
                                        value={form.data.sender_name}
                                        onChange={(event) =>
                                            form.setField(
                                                'sender_name',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('sender_name')}
                                        aria-invalid={form.invalid('sender_name') || undefined}
                                    />
                                    <FieldError>{form.error('sender_name')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('sender_email') || undefined}>
                                    <FieldLabel htmlFor="sender_email">
                                        Sender email <span aria-hidden>*</span>
                                    </FieldLabel>
                                    <Input
                                        id="sender_email"
                                        type="email"
                                        value={form.data.sender_email}
                                        onChange={(event) =>
                                            form.setField(
                                                'sender_email',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('sender_email')}
                                        aria-invalid={form.invalid('sender_email') || undefined}
                                    />
                                    <FieldError>{form.error('sender_email')}</FieldError>
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={form.invalid('reply_to') || undefined}>
                                    <FieldLabel htmlFor="reply_to">Reply-to email</FieldLabel>
                                    <Input
                                        id="reply_to"
                                        type="email"
                                        value={form.data.reply_to}
                                        onChange={(event) =>
                                            form.setField('reply_to', event.target.value)
                                        }
                                        onBlur={() => form.touch('reply_to')}
                                        aria-invalid={form.invalid('reply_to') || undefined}
                                    />
                                    <FieldError>{form.error('reply_to')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('bcc') || undefined}>
                                    <FieldLabel htmlFor="bcc">BCC email</FieldLabel>
                                    <Input
                                        id="bcc"
                                        type="email"
                                        value={form.data.bcc}
                                        onChange={(event) =>
                                            form.setField('bcc', event.target.value)
                                        }
                                        onBlur={() => form.touch('bcc')}
                                        aria-invalid={form.invalid('bcc') || undefined}
                                    />
                                    <FieldError>{form.error('bcc')}</FieldError>
                                </Field>
                            </div>

                            <Field data-invalid={form.invalid('signature') || undefined}>
                                <FieldLabel htmlFor="signature">Signature</FieldLabel>
                                <Textarea
                                    id="signature"
                                    rows={5}
                                    value={form.data.signature}
                                    onChange={(event) =>
                                        form.setField('signature', event.target.value)
                                    }
                                    onBlur={() => form.touch('signature')}
                                    aria-invalid={form.invalid('signature') || undefined}
                                />
                                <FieldDescription>
                                    Optional footer appended to emails sent through this provider.
                                </FieldDescription>
                                <FieldError>{form.error('signature')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('status') || undefined}>
                                <FieldSet>
                                    <FieldLegend>Status</FieldLegend>
                                    <FieldDescription>
                                        Keep inactive providers hidden from template selection.
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
                                                value as EmailProviderFormValues['status'],
                                            );
                                        }}
                                        variant="outline"
                                        className="w-full flex-wrap"
                                        aria-invalid={form.invalid('status') || undefined}
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
                            <ServerIcon className="size-4 text-muted-foreground" />
                            <CardTitle>SMTP settings</CardTitle>
                        </div>
                        <CardDescription>
                            Enter the server credentials used to deliver outgoing email.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={form.invalid('smtp_host') || undefined}>
                                    <FieldLabel htmlFor="smtp_host">
                                        SMTP host <span aria-hidden>*</span>
                                    </FieldLabel>
                                    <Input
                                        id="smtp_host"
                                        value={form.data.smtp_host}
                                        onChange={(event) =>
                                            form.setField('smtp_host', event.target.value)
                                        }
                                        onBlur={() => form.touch('smtp_host')}
                                        aria-invalid={form.invalid('smtp_host') || undefined}
                                    />
                                    <FieldError>{form.error('smtp_host')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('smtp_port') || undefined}>
                                    <FieldLabel htmlFor="smtp_port">
                                        SMTP port <span aria-hidden>*</span>
                                    </FieldLabel>
                                    <Input
                                        id="smtp_port"
                                        inputMode="numeric"
                                        value={form.data.smtp_port}
                                        onChange={(event) =>
                                            form.setField('smtp_port', event.target.value)
                                        }
                                        onBlur={() => form.touch('smtp_port')}
                                        aria-invalid={form.invalid('smtp_port') || undefined}
                                    />
                                    <FieldError>{form.error('smtp_port')}</FieldError>
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={form.invalid('smtp_user') || undefined}>
                                    <FieldLabel htmlFor="smtp_user">
                                        SMTP username <span aria-hidden>*</span>
                                    </FieldLabel>
                                    <Input
                                        id="smtp_user"
                                        value={form.data.smtp_user}
                                        onChange={(event) =>
                                            form.setField('smtp_user', event.target.value)
                                        }
                                        onBlur={() => form.touch('smtp_user')}
                                        aria-invalid={form.invalid('smtp_user') || undefined}
                                    />
                                    <FieldError>{form.error('smtp_user')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('smtp_password') || undefined}>
                                    <FieldLabel htmlFor="smtp_password">
                                        SMTP password
                                        {mode === 'create' ? (
                                            <span aria-hidden> *</span>
                                        ) : null}
                                    </FieldLabel>
                                    <PasswordInput
                                        id="smtp_password"
                                        value={form.data.smtp_password}
                                        onChange={(event) =>
                                            form.setField(
                                                'smtp_password',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('smtp_password')}
                                        aria-invalid={form.invalid('smtp_password') || undefined}
                                    />
                                    {mode === 'edit' ? (
                                        <FieldDescription>
                                            Leave blank to keep the current password.
                                        </FieldDescription>
                                    ) : null}
                                    <FieldError>{form.error('smtp_password')}</FieldError>
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={form.invalid('smtp_encryption') || undefined}>
                                    <FieldLabel htmlFor="smtp_encryption">
                                        Encryption <span aria-hidden>*</span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="smtp_encryption"
                                        name="smtp_encryption"
                                        value={form.data.smtp_encryption}
                                        onChange={(event) =>
                                            form.setField(
                                                'smtp_encryption',
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('smtp_encryption')}
                                        aria-invalid={form.invalid('smtp_encryption') || undefined}
                                        className="w-full"
                                    >
                                        {encryptionOptions.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.error('smtp_encryption')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('order') || undefined}>
                                    <FieldLabel htmlFor="order">Sort order</FieldLabel>
                                    <Input
                                        id="order"
                                        type="number"
                                        min="0"
                                        step="1"
                                        value={form.data.order}
                                        onChange={(event) =>
                                            form.setField('order', event.target.value)
                                        }
                                        onBlur={() => form.touch('order')}
                                        aria-invalid={form.invalid('order') || undefined}
                                    />
                                    <FieldDescription>
                                        Lower values appear first in provider lists.
                                    </FieldDescription>
                                    <FieldError>{form.error('order')}</FieldError>
                                </Field>
                            </div>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('app.masters.email.providers.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to providers
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner className="size-4" />
                    ) : (
                        <SaveIcon data-icon="inline-start" />
                    )}
                    {mode === 'create' ? 'Create provider' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
