import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    FileIcon,
    MessageSquareIcon,
    PaperclipIcon,
    SendIcon,
    XIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { hasMeaningfulNoteContent, NoteRichTextEditor } from '@/components/notes/note-rich-text-editor';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import {
    Avatar,
    AvatarFallback,
} from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
} from '@/components/ui/field';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedData } from '@/types';

const maxAttachments = 5;
const maxAttachmentBytes = 5 * 1024 * 1024;
const acceptedAttachmentExtensions = new Set([
    'jpg',
    'jpeg',
    'png',
    'gif',
    'pdf',
    'doc',
    'docx',
    'txt',
    'zip',
]);

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

function createThreadEntry(
    entry: Omit<ThreadEntry, 'badgeVariant'> & {
        badgeVariant: ThreadEntry['badgeVariant'];
    },
): ThreadEntry {
    return entry;
}

type ThreadEntry = {
    key: string;
    authorName: string;
    badgeLabel: string;
    badgeVariant: 'success' | 'secondary' | 'warning' | 'danger';
    metaLabel: string;
    contentHtml: string;
    attachments: TicketAttachment[];
    createdAt: string | null;
    sortPriority: number;
};

function getPlainTextLength(value: string): number {
    return value
        .replace(/<[^>]+>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/\s+/g, ' ')
        .trim().length;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function getAttachmentExtension(file: File): string {
    return file.name.split('.').pop()?.toLowerCase() ?? '';
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((segment) => segment.charAt(0).toUpperCase())
        .join('');
}

function statusVariant(status: string): 'success' | 'warning' | 'secondary' | 'danger' {
    switch (status.toLowerCase()) {
        case 'open':
            return 'success';
        case 'pending':
        case 'on_hold':
            return 'warning';
        case 'resolved':
        case 'closed':
            return 'secondary';
        default:
            return 'danger';
    }
}

function AttachmentLinks({ attachments }: { attachments: TicketAttachment[] }) {
    if (attachments.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {attachments.map((attachment) => (
                <Button key={attachment.url} asChild size="sm" variant="outline">
                    <a href={attachment.url} target="_blank" rel="noreferrer">
                        <PaperclipIcon data-icon="inline-start" />
                        {attachment.name}
                    </a>
                </Button>
            ))}
        </div>
    );
}

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
    const { auth } = usePage<SharedData>().props;
    const currentUserName = auth.user?.name?.trim() || 'You';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Support', href: route('agency.tickets.index') },
        { title: `Ticket #${ticket.id}`, href: route('agency.tickets.show', ticket.id) },
    ];

    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [isDragOver, setIsDragOver] = useState(false);

    const form = useAppForm<{
        message: string;
        attachments: File[];
    }>({
        defaults: {
            message: '',
            attachments: [],
        },
        rememberKey: `agency.tickets.show.${ticket.id}`,
        dontRemember: ['attachments'],
        dirtyGuard: { enabled: true },
        rules: {
            message: [
                (value) =>
                    hasMeaningfulNoteContent(value)
                        ? undefined
                        : 'Reply is required.',
                (value) =>
                    getPlainTextLength(value) >= 5
                        ? undefined
                        : 'Reply must be at least 5 characters.',
            ],
            attachments: [
                (value) =>
                    value.length <= maxAttachments
                        ? undefined
                        : `You can attach up to ${maxAttachments} files.`,
                (value) => {
                    const oversizedFile = value.find(
                        (file) => file.size > maxAttachmentBytes,
                    );

                    if (oversizedFile) {
                        return `${oversizedFile.name} exceeds the 5 MB file size limit.`;
                    }

                    return undefined;
                },
                (value) => {
                    const invalidFile = value.find(
                        (file) =>
                            !acceptedAttachmentExtensions.has(
                                getAttachmentExtension(file),
                            ),
                    );

                    if (invalidFile) {
                        return `${invalidFile.name} is not a supported file type.`;
                    }

                    return undefined;
                },
            ],
        },
    });

    const closedStatuses = ['closed', 'cancelled'];
    const canReply = !closedStatuses.includes(ticket.status);
    const ticketBadgeVariant: ThreadEntry['badgeVariant'] = 'success';
    const threadEntries: ThreadEntry[] = [
        createThreadEntry({
            key: `ticket-${ticket.id}`,
            authorName: currentUserName,
            badgeLabel: 'You',
            badgeVariant: ticketBadgeVariant,
            metaLabel: 'Opened this ticket',
            contentHtml: ticket.description_html,
            attachments: ticket.attachments,
            createdAt: ticket.created_at,
            sortPriority: 0,
        }),
        ...ticket.replies.map((reply) =>
            {
                const badgeVariant: ThreadEntry['badgeVariant'] = reply.is_staff
                    ? 'secondary'
                    : 'success';

                return createThreadEntry({
                    key: `reply-${reply.id}`,
                    authorName: reply.author_name,
                    badgeLabel: reply.is_staff ? 'System' : 'You',
                    badgeVariant,
                    metaLabel: reply.is_staff ? 'Support response' : 'Reply from your side',
                    contentHtml: reply.content_html,
                    attachments: reply.attachments,
                    createdAt: reply.created_at,
                    sortPriority: 1,
                });
            },
        ),
    ].sort((left, right) => {
        const rightTimestamp = right.createdAt ? Date.parse(right.createdAt) : 0;
        const leftTimestamp = left.createdAt ? Date.parse(left.createdAt) : 0;

        if (rightTimestamp !== leftTimestamp) {
            return rightTimestamp - leftTimestamp;
        }

        return right.sortPriority - left.sortPriority;
    });

    const setAttachments = (files: File[]) => {
        form.setField('attachments', files.slice(0, maxAttachments));
    };

    const mergeAttachments = (incomingFiles: File[]) => {
        const nextFiles = [...form.data.attachments, ...incomingFiles].slice(
            0,
            maxAttachments,
        );

        setAttachments(nextFiles);
    };

    const handleAttachmentSelection = (
        event: React.ChangeEvent<HTMLInputElement>,
    ) => {
        mergeAttachments(Array.from(event.target.files ?? []));
        event.target.value = '';
    };

    const handleDrop = (event: React.DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setIsDragOver(false);
        mergeAttachments(Array.from(event.dataTransfer.files ?? []));
    };

    const removeAttachment = (fileIndex: number) => {
        setAttachments(
            form.data.attachments.filter((_, index) => index !== fileIndex),
        );
    };

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit('post', route('agency.tickets.reply', ticket.id), {
            forceFormData: true,
            successToast: {
                title: 'Reply sent',
                description: 'Your reply has been added to the ticket thread.',
            },
            onSuccess: () => {
                form.reset('message', 'attachments');
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Ticket #${ticket.id}`}
            description="Track the conversation and reply from the same thread."
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('agency.tickets.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Tickets
                    </Link>
                </Button>
            }
        >
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                {form.dirtyGuardDialog}

                <Card className="border-border/70 shadow-xs">
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex flex-col gap-2">
                                <CardTitle className="text-2xl">{ticket.subject}</CardTitle>
                                <CardDescription className="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                                    <Badge variant={statusVariant(ticket.status)}>
                                        {ticket.status}
                                    </Badge>
                                    <span>Ticket #{ticket.id}</span>
                                    <span>Created {formatDateTime(ticket.created_at)}</span>
                                    <span>Updated {formatDateTime(ticket.updated_at)}</span>
                                </CardDescription>
                            </div>

                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <MessageSquareIcon className="size-4" />
                                    <span>{threadEntries.length} messages</span>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {canReply ? (
                    <form className="mx-auto flex w-full max-w-3xl flex-col gap-3" onSubmit={handleSubmit} noValidate>
                        <FormErrorSummary errors={form.errors} minMessages={2} />

                        <Field data-invalid={form.invalid('message') || undefined}>
                            <div className="overflow-hidden rounded-2xl border border-border/70 bg-background shadow-xs">
                                <NoteRichTextEditor
                                    id="message"
                                    value={form.data.message}
                                    onChange={(value) => form.setField('message', value)}
                                    onBlur={() => form.touch('message')}
                                    invalid={form.invalid('message')}
                                    placeholder="Type your reply here... Be detailed to help us assist you better."
                                    className="min-h-[160px]"
                                />
                            </div>
                            <FieldError className="mt-2">{form.error('message')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('attachments') || undefined}>
                            <label
                                htmlFor="reply_attachments"
                                className={`flex min-h-16 cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-3 text-center transition-colors ${
                                    isDragOver
                                        ? 'border-foreground/40 bg-muted/70'
                                        : 'border-border bg-background hover:border-foreground/30 hover:bg-muted/30'
                                }`}
                                onDragEnter={() => setIsDragOver(true)}
                                onDragLeave={() => setIsDragOver(false)}
                                onDragOver={(event) => event.preventDefault()}
                                onDrop={handleDrop}
                            >
                                <PaperclipIcon className="size-4 text-muted-foreground" />
                                <p className="text-sm text-muted-foreground">
                                    Attach files (Drag &amp; drop or <span className="font-medium text-foreground">browse</span>)
                                </p>
                            </label>

                            <input
                                ref={fileInputRef}
                                id="reply_attachments"
                                type="file"
                                multiple
                                className="sr-only"
                                onChange={handleAttachmentSelection}
                                accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip"
                            />

                            {form.data.attachments.length > 0 ? (
                                <div className="mt-2 grid gap-1.5">
                                    {form.data.attachments.map((file, index) => (
                                        <div
                                            key={`${file.name}-${file.lastModified}-${index}`}
                                            className="flex items-center justify-between gap-3 rounded-xl border border-border/70 bg-muted/20 px-3 py-2"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                    <FileIcon className="size-4" />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-foreground">
                                                        {file.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatFileSize(file.size)}
                                                    </p>
                                                </div>
                                            </div>

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                onClick={() => removeAttachment(index)}
                                                aria-label={`Remove ${file.name}`}
                                            >
                                                <XIcon className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            ) : null}

                            <FieldError className="mt-2">{form.error('attachments')}</FieldError>
                        </Field>

                        <Button
                            type="submit"
                            size="xl"
                            className="w-full rounded-xl bg-foreground text-background hover:bg-foreground/90"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                'Sending Reply...'
                            ) : (
                                <>
                                    Send Reply
                                    <SendIcon data-icon="inline-end" />
                                </>
                            )}
                        </Button>
                    </form>
                ) : (
                    <Card className="border-border/70 shadow-xs">
                        <CardHeader>
                            <CardTitle>Replies Closed</CardTitle>
                            <CardDescription>
                                This ticket is currently {ticket.status}. Open a new ticket if you still need help.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                <div className="relative flex flex-col gap-4 before:absolute before:bottom-0 before:left-1/2 before:top-0 before:hidden before:w-px before:-translate-x-1/2 before:bg-border/70 md:before:block">
                    {threadEntries.map((entry) => (
                        <Card
                            key={entry.key}
                            className="relative mx-auto w-full max-w-3xl border-border/70 shadow-xs"
                        >
                            <CardHeader>
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <Avatar size="lg">
                                            <AvatarFallback>
                                                {getInitials(entry.authorName)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="flex flex-col gap-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <CardTitle className="text-base">
                                                    {entry.authorName}
                                                </CardTitle>
                                                <Badge variant={entry.badgeVariant}>
                                                    {entry.badgeLabel}
                                                </Badge>
                                            </div>
                                            <CardDescription>{entry.metaLabel}</CardDescription>
                                        </div>
                                    </div>

                                    <span className="text-xs text-muted-foreground">
                                        {formatDateTime(entry.createdAt)}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div
                                    className="prose prose-sm max-w-none text-foreground"
                                    dangerouslySetInnerHTML={{
                                        __html: entry.contentHtml,
                                    }}
                                />
                                <AttachmentLinks attachments={entry.attachments} />
                            </CardContent>
                        </Card>
                    ))}

                    <div className="flex justify-center pt-2">
                        <Badge variant="outline">
                            Ticket opened {ticket.created_at ? new Date(ticket.created_at).toLocaleDateString('en-GB') : 'N/A'}
                        </Badge>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
