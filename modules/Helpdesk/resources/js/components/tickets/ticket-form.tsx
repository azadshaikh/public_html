import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
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
    HelpdeskOption,
    TicketAttachment,
    TicketFormValues,
} from '../../types/helpdesk';

type TicketFormProps = {
    mode: 'create' | 'edit';
    ticket?: {
        id: number;
        ticket_number: string;
        subject: string;
        status: string;
    };
    initialValues: TicketFormValues;
    departments: HelpdeskOption[];
    users: HelpdeskOption[];
    priorityOptions: HelpdeskOption[];
    statusOptions: HelpdeskOption[];
    existingAttachments: TicketAttachment[];
};

export default function TicketForm({
    mode,
    ticket,
    initialValues,
    departments,
    users,
    priorityOptions,
    statusOptions,
    existingAttachments,
}: TicketFormProps) {
    const form = useAppForm<TicketFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'helpdesk.tickets.create'
                : `helpdesk.tickets.edit.${ticket?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            subject: [formValidators.required('Subject')],
            department_id: [formValidators.required('Department')],
            status: [formValidators.required('Status')],
            priority: [formValidators.required('Priority')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('helpdesk.tickets.store')
            : route('helpdesk.tickets.update', ticket!.id);
    const submitLabel =
        mode === 'create' ? 'Create Ticket' : 'Update Ticket';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            forceFormData: true,
            successToast: {
                title:
                    mode === 'create'
                        ? 'Ticket created'
                        : 'Ticket updated',
                description:
                    mode === 'create'
                        ? 'The ticket has been created successfully.'
                        : 'The ticket has been updated successfully.',
            },
        });
    };

    const handleFileChange = (files: FileList | null) => {
        form.setField(
            'attachments',
            files && files.length > 0 ? Array.from(files) : null,
        );
    };

    return (
        <form
            className="flex flex-col gap-6"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                {/* Main content */}
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ticket Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        data-invalid={
                                            form.invalid('ticket_number') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="ticket_number">
                                            Ticket Number
                                        </FieldLabel>
                                        <Input
                                            id="ticket_number"
                                            value={form.data.ticket_number}
                                            readOnly
                                            className="bg-muted"
                                        />
                                        <FieldError>
                                            {form.error('ticket_number')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('department_id') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="department_id">
                                            Department{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <NativeSelect
                                            id="department_id"
                                            value={form.data.department_id}
                                            onChange={(e) =>
                                                form.setField(
                                                    'department_id',
                                                    e.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                form.invalid('department_id') ||
                                                undefined
                                            }
                                        >
                                            <NativeSelectOption value="">
                                                Select department…
                                            </NativeSelectOption>
                                            {departments.map((option) => (
                                                <NativeSelectOption
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                        <FieldError>
                                            {form.error('department_id')}
                                        </FieldError>
                                    </Field>
                                </div>

                                <Field
                                    data-invalid={
                                        form.invalid('subject') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="subject">
                                        Subject{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="subject"
                                        value={form.data.subject}
                                        onChange={(e) =>
                                            form.setField(
                                                'subject',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('subject')}
                                        aria-invalid={
                                            form.invalid('subject') || undefined
                                        }
                                        placeholder="Brief summary of the issue…"
                                    />
                                    <FieldError>
                                        {form.error('subject')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('description') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="description">
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id="description"
                                        rows={6}
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setField(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('description')}
                                        aria-invalid={
                                            form.invalid('description') ||
                                            undefined
                                        }
                                        placeholder="Describe the issue in detail…"
                                    />
                                    <FieldError>
                                        {form.error('description')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* Attachments */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Attachments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                {existingAttachments.length > 0 && (
                                    <div className="space-y-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Current Attachments
                                        </span>
                                        <ul className="space-y-1">
                                            {existingAttachments.map(
                                                (att, idx) => (
                                                    <li
                                                        key={idx}
                                                        className="flex items-center gap-2 text-sm"
                                                    >
                                                        {att.url ? (
                                                            <a
                                                                href={att.url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="text-primary hover:underline"
                                                            >
                                                                {att.file_name}
                                                            </a>
                                                        ) : (
                                                            <span>
                                                                {att.file_name}
                                                            </span>
                                                        )}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}

                                <Field
                                    data-invalid={
                                        form.invalid('attachments') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="attachments">
                                        {existingAttachments.length > 0
                                            ? 'Add More Files'
                                            : 'Upload Files'}
                                    </FieldLabel>
                                    <Input
                                        id="attachments"
                                        type="file"
                                        multiple
                                        onChange={(e) =>
                                            handleFileChange(e.target.files)
                                        }
                                        aria-invalid={
                                            form.invalid('attachments') ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('attachments')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>People</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('user_id') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="user_id">
                                        Raised By
                                    </FieldLabel>
                                    <NativeSelect
                                        id="user_id"
                                        value={form.data.user_id}
                                        onChange={(e) =>
                                            form.setField(
                                                'user_id',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('user_id') || undefined
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select user…
                                        </NativeSelectOption>
                                        {users.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('user_id')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('assigned_to') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="assigned_to">
                                        Assigned To
                                    </FieldLabel>
                                    <NativeSelect
                                        id="assigned_to"
                                        value={form.data.assigned_to}
                                        onChange={(e) =>
                                            form.setField(
                                                'assigned_to',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('assigned_to') ||
                                            undefined
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Unassigned
                                        </NativeSelectOption>
                                        {users.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('assigned_to')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Status &amp; Priority</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('status') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="status">
                                        Status{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setField(
                                                'status',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('status') || undefined
                                        }
                                    >
                                        {statusOptions.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
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
                                        form.invalid('priority') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="priority">
                                        Priority{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="priority"
                                        value={form.data.priority}
                                        onChange={(e) =>
                                            form.setField(
                                                'priority',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('priority') ||
                                            undefined
                                        }
                                    >
                                        {priorityOptions.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('priority')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <Spinner className="mr-2" />
                        ) : null}
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}
