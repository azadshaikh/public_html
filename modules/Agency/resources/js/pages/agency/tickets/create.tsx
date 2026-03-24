import { useForm } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Support', href: route('agency.tickets.index') },
    { title: 'Create Ticket', href: route('agency.tickets.create') },
];

export default function AgencyTicketCreate() {
    const form = useForm<{
        subject: string;
        message: string;
        attachments: File[];
    }>({
        subject: '',
        message: '',
        attachments: [],
    });

    const handleAttachmentChange = (event: ChangeEvent<HTMLInputElement>) => {
        form.setData('attachments', Array.from(event.target.files ?? []));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(route('agency.tickets.store'));
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Support Ticket"
            description="Describe the issue in as much detail as possible so the support team can help quickly."
        >
            <Card className="mx-auto max-w-3xl">
                <CardHeader>
                    <CardTitle>New Ticket</CardTitle>
                    <CardDescription>
                        Attach screenshots, logs, or documents if they help explain the issue.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-6" onSubmit={handleSubmit}>
                        <FormErrorSummary errors={form.errors} />

                        <FieldGroup>
                            <Field data-invalid={form.errors.subject || undefined}>
                                <FieldLabel htmlFor="subject">Subject</FieldLabel>
                                <Input
                                    id="subject"
                                    value={form.data.subject}
                                    onChange={(event) =>
                                        form.setData('subject', event.target.value)
                                    }
                                    placeholder="Brief description of the issue"
                                />
                                <FieldError>{form.errors.subject}</FieldError>
                            </Field>

                            <Field data-invalid={form.errors.message || undefined}>
                                <FieldLabel htmlFor="message">Message</FieldLabel>
                                <Textarea
                                    id="message"
                                    rows={8}
                                    value={form.data.message}
                                    onChange={(event) =>
                                        form.setData('message', event.target.value)
                                    }
                                    placeholder="Explain what happened, what you expected, and what you have tried already."
                                />
                                <FieldError>{form.errors.message}</FieldError>
                            </Field>

                            <Field data-invalid={form.errors.attachments || undefined}>
                                <FieldLabel htmlFor="attachments">Attachments</FieldLabel>
                                <Input
                                    id="attachments"
                                    type="file"
                                    multiple
                                    onChange={handleAttachmentChange}
                                />
                                <FieldError>{form.errors.attachments}</FieldError>
                            </Field>
                        </FieldGroup>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                Submit Ticket
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
