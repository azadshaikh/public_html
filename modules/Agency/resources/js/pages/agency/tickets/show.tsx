import { useForm } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
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

type TicketAttachment = {
    name: string;
    url: string;
};

type TicketReply = {
    id: number;
    author_name: string;
    is_staff: boolean;
    content_html: string;
    created_at: string | null;
    attachments: TicketAttachment[];
};

type TicketData = {
    id: number;
    subject: string;
    status: string;
    description_html: string;
    created_at: string | null;
    updated_at: string | null;
    attachments: TicketAttachment[];
    replies: TicketReply[];
};

type AgencyTicketShowPageProps = {
    ticket: TicketData;
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export default function AgencyTicketShow({
    ticket,
}: AgencyTicketShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Support', href: route('agency.tickets.index') },
        { title: `Ticket #${ticket.id}`, href: route('agency.tickets.show', ticket.id) },
    ];

    const form = useForm<{
        message: string;
        attachments: File[];
    }>({
        message: '',
        attachments: [],
    });

    const closedStatuses = ['closed', 'cancelled'];
    const canReply = !closedStatuses.includes(ticket.status);

    const handleAttachmentChange = (event: ChangeEvent<HTMLInputElement>) => {
        form.setData('attachments', Array.from(event.target.files ?? []));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(route('agency.tickets.reply', ticket.id));
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Ticket #${ticket.id}`}
            description="Track the conversation and reply from the same thread."
            headerActions={
                <Button asChild variant="outline">
                    <a href={route('agency.tickets.index')}>Back to Tickets</a>
                </Button>
            }
        >
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <CardTitle>{ticket.subject}</CardTitle>
                                <CardDescription>
                                    Opened {formatDateTime(ticket.created_at)} and
                                    updated {formatDateTime(ticket.updated_at)}
                                </CardDescription>
                            </div>
                            <Badge variant="secondary">{ticket.status}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div
                            className="prose prose-sm max-w-none"
                            dangerouslySetInnerHTML={{
                                __html: ticket.description_html,
                            }}
                        />

                        {ticket.attachments.length > 0 ? (
                            <div className="space-y-2">
                                <p className="text-sm font-medium">Attachments</p>
                                <div className="flex flex-wrap gap-2">
                                    {ticket.attachments.map((attachment) => (
                                        <Button key={attachment.url} asChild size="sm" variant="outline">
                                            <a href={attachment.url} target="_blank" rel="noreferrer">
                                                {attachment.name}
                                            </a>
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>

                {canReply ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Reply</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form className="space-y-6" onSubmit={handleSubmit}>
                                <FieldGroup>
                                    <Field data-invalid={form.errors.message || undefined}>
                                        <FieldLabel htmlFor="message">Message</FieldLabel>
                                        <Textarea
                                            id="message"
                                            rows={6}
                                            value={form.data.message}
                                            onChange={(event) =>
                                                form.setData('message', event.target.value)
                                            }
                                        />
                                        <FieldError>{form.errors.message}</FieldError>
                                    </Field>
                                    <Field data-invalid={form.errors.attachments || undefined}>
                                        <FieldLabel htmlFor="reply_attachments">Attachments</FieldLabel>
                                        <Input
                                            id="reply_attachments"
                                            type="file"
                                            multiple
                                            onChange={handleAttachmentChange}
                                        />
                                        <FieldError>{form.errors.attachments}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={form.processing}>
                                        Send Reply
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}

                <div className="space-y-4">
                    {ticket.replies.map((reply) => (
                        <Card key={reply.id}>
                            <CardHeader>
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-base">
                                            {reply.author_name}
                                        </CardTitle>
                                        <CardDescription>
                                            {formatDateTime(reply.created_at)}
                                        </CardDescription>
                                    </div>
                                    <Badge variant={reply.is_staff ? 'success' : 'secondary'}>
                                        {reply.is_staff ? 'Agent' : 'You'}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div
                                    className="prose prose-sm max-w-none"
                                    dangerouslySetInnerHTML={{
                                        __html: reply.content_html,
                                    }}
                                />
                                {reply.attachments.length > 0 ? (
                                    <div className="flex flex-wrap gap-2">
                                        {reply.attachments.map((attachment) => (
                                            <Button key={attachment.url} asChild size="sm" variant="outline">
                                                <a href={attachment.url} target="_blank" rel="noreferrer">
                                                    {attachment.name}
                                                </a>
                                            </Button>
                                        ))}
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
