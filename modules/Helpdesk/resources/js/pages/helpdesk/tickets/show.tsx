import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    ClockIcon,
    DownloadIcon,
    LockIcon,
    MessageSquareIcon,
    PaperclipIcon,
    PencilIcon,
    RefreshCwIcon,
    SendIcon,
    Trash2Icon,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    ActivityEntry,
    HelpdeskOption,
    TicketReply,
    TicketReplyFormValues,
    TicketShowPageProps,
} from '../../../types/helpdesk';

// =========================================================================
// HELPERS
// =========================================================================

function DetailRow({
    label,
    value,
    icon,
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}) {
    if (value === null || value === undefined || value === '') return null;

    return (
        <div className="flex items-start gap-3 py-2">
            {icon && (
                <span className="mt-0.5 text-muted-foreground">{icon}</span>
            )}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

function Avatar({
    src,
    name,
}: {
    src: string | null;
    name: string;
}) {
    if (src) {
        return (
            <img
                src={src}
                alt={name}
                className="size-8 rounded-full object-cover"
            />
        );
    }

    return (
        <div className="flex size-8 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground">
            {name.charAt(0).toUpperCase()}
        </div>
    );
}

function priorityBadgeVariant(
    priority: string,
): 'info' | 'warning' | 'danger' | 'destructive' | 'secondary' {
    switch (priority) {
        case 'low':
            return 'info';
        case 'medium':
            return 'warning';
        case 'high':
            return 'danger';
        case 'critical':
            return 'destructive';
        default:
            return 'secondary';
    }
}

function statusBadgeVariant(
    status: string,
): 'success' | 'warning' | 'info' | 'secondary' | 'outline' | 'danger' {
    switch (status) {
        case 'open':
            return 'success';
        case 'pending':
            return 'warning';
        case 'resolved':
            return 'info';
        case 'on_hold':
            return 'secondary';
        case 'closed':
            return 'outline';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

// =========================================================================
// REPLY ITEM
// =========================================================================

function ReplyItem({
    reply,
    ticketId,
    canDelete,
}: {
    reply: TicketReply;
    ticketId: number;
    canDelete: boolean;
}) {
    const handleDeleteReply = () => {
        if (!window.confirm('Delete this reply?')) return;
        router.delete(
            route('helpdesk.tickets.reply.delete', [ticketId, reply.id]),
            { preserveScroll: true },
        );
    };

    return (
        <div
            className={`relative rounded-lg border p-4 ${
                reply.is_internal
                    ? 'border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/20'
                    : 'bg-card'
            }`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Avatar src={reply.reply_by_avatar} name={reply.reply_by_name} />
                    <div>
                        <div className="text-sm font-medium">
                            {reply.reply_by_name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {reply.created_at}
                        </div>
                    </div>
                    {reply.is_internal && (
                        <Badge variant="warning" className="ml-1">
                            <LockIcon className="mr-1 size-3" />
                            Internal
                        </Badge>
                    )}
                </div>

                {canDelete && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 text-muted-foreground hover:text-destructive"
                        onClick={handleDeleteReply}
                    >
                        <Trash2Icon className="size-3.5" />
                    </Button>
                )}
            </div>

            <div className="mt-3 whitespace-pre-wrap text-sm">
                {reply.content}
            </div>

            {reply.attachments.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {reply.attachments.map((att, idx) => (
                        <a
                            key={idx}
                            href={att.url ?? '#'}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 rounded-md border bg-muted/50 px-2.5 py-1.5 text-xs text-foreground transition-colors hover:bg-muted"
                        >
                            <PaperclipIcon className="size-3" />
                            {att.file_name}
                            <DownloadIcon className="size-3 text-muted-foreground" />
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}

// =========================================================================
// REPLY FORM
// =========================================================================

function ReplyForm({
    ticketId,
    initialValues,
    departments,
    users,
    priorityOptions,
    statusOptions,
}: {
    ticketId: number;
    initialValues: TicketReplyFormValues;
    departments: HelpdeskOption[];
    users: HelpdeskOption[];
    priorityOptions: HelpdeskOption[];
    statusOptions: HelpdeskOption[];
}) {
    const form = useAppForm<TicketReplyFormValues>({
        rememberKey: `ticket-reply-${ticketId}`,
        defaults: initialValues,
        rules: {
            content: [formValidators.required('Reply content')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('helpdesk.tickets.reply.store', ticketId), {
            preserveScroll: true,
            forceFormData: true,
            successToast: {
                title: 'Reply added',
                description: 'Your reply has been posted successfully.',
            },
            onSuccess: () => {
                form.reset('content', 'attachments', 'is_internal');
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <SendIcon className="size-4" />
                    Add Reply
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} noValidate>
                    <FieldGroup>
                        {/* Ticket status updates (inline) */}
                        <div className="grid gap-4 sm:grid-cols-4">
                            <Field>
                                <FieldLabel htmlFor="reply_department_id">
                                    Department
                                </FieldLabel>
                                <NativeSelect
                                    id="reply_department_id"
                                    value={form.data.department_id}
                                    onChange={(e) =>
                                        form.setField(
                                            'department_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {departments.map((opt) => (
                                        <NativeSelectOption
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="reply_assigned_to">
                                    Assigned To
                                </FieldLabel>
                                <NativeSelect
                                    id="reply_assigned_to"
                                    value={form.data.assigned_to}
                                    onChange={(e) =>
                                        form.setField(
                                            'assigned_to',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <NativeSelectOption value="">
                                        Unassigned
                                    </NativeSelectOption>
                                    {users.map((opt) => (
                                        <NativeSelectOption
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="reply_priority">
                                    Priority
                                </FieldLabel>
                                <NativeSelect
                                    id="reply_priority"
                                    value={form.data.priority}
                                    onChange={(e) =>
                                        form.setField(
                                            'priority',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {priorityOptions.map((opt) => (
                                        <NativeSelectOption
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="reply_status">
                                    Status
                                </FieldLabel>
                                <NativeSelect
                                    id="reply_status"
                                    value={form.data.status}
                                    onChange={(e) =>
                                        form.setField(
                                            'status',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {statusOptions.map((opt) => (
                                        <NativeSelectOption
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </Field>
                        </div>

                        {/* Reply content */}
                        <Field
                            data-invalid={
                                form.invalid('content') || undefined
                            }
                        >
                            <FieldLabel htmlFor="reply_content">
                                Reply{' '}
                                <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Textarea
                                id="reply_content"
                                rows={4}
                                value={form.data.content}
                                onChange={(e) =>
                                    form.setField('content', e.target.value)
                                }
                                onBlur={() => form.touch('content')}
                                aria-invalid={
                                    form.invalid('content') || undefined
                                }
                                placeholder="Write your reply…"
                            />
                            <FieldError>{form.error('content')}</FieldError>
                        </Field>

                        {/* Attachments + Internal + Submit */}
                        <div className="flex flex-wrap items-end gap-4">
                            <Field className="flex-1">
                                <FieldLabel htmlFor="reply_attachments">
                                    Attachments
                                </FieldLabel>
                                <Input
                                    id="reply_attachments"
                                    type="file"
                                    multiple
                                    onChange={(e) =>
                                        form.setField(
                                            'attachments',
                                            e.target.files
                                                ? Array.from(e.target.files)
                                                : null,
                                        )
                                    }
                                />
                            </Field>

                            <label className="flex cursor-pointer items-center gap-2 pb-2 text-sm">
                                <Checkbox
                                    checked={form.data.is_internal}
                                    onCheckedChange={(checked) =>
                                        form.setField(
                                            'is_internal',
                                            checked === true,
                                        )
                                    }
                                />
                                <LockIcon className="size-3.5 text-muted-foreground" />
                                Internal note
                            </label>

                            <Button
                                type="submit"
                                disabled={form.processing}
                            >
                                {form.processing ? (
                                    <Spinner className="mr-2" />
                                ) : (
                                    <SendIcon data-icon="inline-start" />
                                )}
                                Send Reply
                            </Button>
                        </div>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

// =========================================================================
// ACTIVITY LOG
// =========================================================================

function ActivityLog({ activities }: { activities: ActivityEntry[] }) {
    const [expanded, setExpanded] = useState(false);
    const visible = expanded ? activities : activities.slice(0, 5);

    if (activities.length === 0) return null;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ClockIcon className="size-4" />
                    Activity
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    {visible.map((activity) => (
                        <div
                            key={activity.id}
                            className="flex items-start gap-3 text-sm"
                        >
                            <div className="mt-1 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted">
                                <ClockIcon className="size-3 text-muted-foreground" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="text-foreground">
                                    {activity.description}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {activity.causer_name && (
                                        <span>
                                            by {activity.causer_name} &middot;{' '}
                                        </span>
                                    )}
                                    {activity.created_at}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {activities.length > 5 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="mt-3"
                        onClick={() => setExpanded(!expanded)}
                    >
                        {expanded
                            ? 'Show less'
                            : `Show all ${activities.length} activities`}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

// =========================================================================
// MAIN PAGE
// =========================================================================

export default function TicketsShow({
    ticket,
    replies,
    replyInitialValues,
    activities,
    departments,
    users,
    priorityOptions,
    statusOptions,
}: TicketShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editHelpdeskTickets;
    const canDelete = page.props.auth.abilities.deleteHelpdeskTickets;
    const canRestore = page.props.auth.abilities.restoreHelpdeskTickets;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Tickets', href: route('helpdesk.tickets.index') },
        {
            title: ticket.ticket_number,
            href: route('helpdesk.tickets.show', ticket.id),
        },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore ticket "${ticket.subject}"?`)) return;
        router.patch(
            route('helpdesk.tickets.restore', ticket.id),
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = () => {
        if (
            !window.confirm(`Move ticket "${ticket.subject}" to trash?`)
        )
            return;
        router.delete(route('helpdesk.tickets.destroy', ticket.id), {
            preserveScroll: true,
        });
    };

    const handleForceDelete = () => {
        if (
            !window.confirm(
                `⚠️ Permanently delete "${ticket.subject}"? This cannot be undone!`,
            )
        )
            return;
        router.delete(
            route('helpdesk.tickets.force-delete', ticket.id),
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={ticket.subject}
            description={`Ticket ${ticket.ticket_number}`}
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('helpdesk.tickets.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {ticket.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}

                    {!ticket.is_trashed && canEdit && (
                        <Button asChild>
                            <Link
                                href={route(
                                    'helpdesk.tickets.edit',
                                    ticket.id,
                                )}
                            >
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {ticket.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This ticket is in the trash.
                        {ticket.deleted_at &&
                            ` Deleted on ${ticket.deleted_at}.`}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    {/* Main thread */}
                    <div className="flex flex-col gap-6">
                        {/* Ticket description */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>{ticket.subject}</CardTitle>
                                    <span className="font-mono text-sm text-muted-foreground">
                                        {ticket.ticket_number}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {ticket.description ? (
                                    <div className="whitespace-pre-wrap text-sm">
                                        {ticket.description}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground italic">
                                        No description provided.
                                    </p>
                                )}

                                {ticket.attachments.length > 0 && (
                                    <div className="mt-4 space-y-2">
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Attachments
                                        </span>
                                        <div className="flex flex-wrap gap-2">
                                            {ticket.attachments.map(
                                                (att, idx) => (
                                                    <a
                                                        key={idx}
                                                        href={att.url ?? '#'}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1.5 rounded-md border bg-muted/50 px-2.5 py-1.5 text-xs text-foreground transition-colors hover:bg-muted"
                                                    >
                                                        <PaperclipIcon className="size-3" />
                                                        {att.file_name}
                                                        <DownloadIcon className="size-3 text-muted-foreground" />
                                                    </a>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Replies thread */}
                        {replies.length > 0 && (
                            <div className="space-y-4">
                                <h3 className="flex items-center gap-2 text-sm font-medium">
                                    <MessageSquareIcon className="size-4" />
                                    Replies ({replies.length})
                                </h3>
                                {replies.map((reply) => (
                                    <ReplyItem
                                        key={reply.id}
                                        reply={reply}
                                        ticketId={ticket.id}
                                        canDelete={!!canEdit}
                                    />
                                ))}
                            </div>
                        )}

                        {/* Reply form */}
                        {!ticket.is_trashed && canEdit && (
                            <ReplyForm
                                ticketId={ticket.id}
                                initialValues={replyInitialValues}
                                departments={departments}
                                users={users}
                                priorityOptions={priorityOptions}
                                statusOptions={statusOptions}
                            />
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="flex flex-col gap-4">
                        {/* Status & Priority */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </span>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                ticket.is_trashed
                                                    ? 'destructive'
                                                    : statusBadgeVariant(
                                                          ticket.status,
                                                      )
                                            }
                                        >
                                            {ticket.is_trashed
                                                ? 'Trashed'
                                                : ticket.status_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Priority
                                    </span>
                                    <div className="mt-1">
                                        <Badge
                                            variant={priorityBadgeVariant(
                                                ticket.priority,
                                            )}
                                        >
                                            {ticket.priority_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Department
                                    </span>
                                    <div className="mt-1 text-sm">
                                        {ticket.department_name}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* People */}
                        <Card>
                            <CardHeader>
                                <CardTitle>People</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <Avatar
                                        src={ticket.requester_avatar}
                                        name={ticket.requester_name}
                                    />
                                    <div className="min-w-0">
                                        <div className="text-xs text-muted-foreground">
                                            Requester
                                        </div>
                                        <div className="truncate text-sm font-medium">
                                            {ticket.requester_name}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Avatar
                                        src={ticket.assigned_to_avatar}
                                        name={ticket.assigned_to_name}
                                    />
                                    <div className="min-w-0">
                                        <div className="text-xs text-muted-foreground">
                                            Assigned To
                                        </div>
                                        <div className="truncate text-sm font-medium">
                                            {ticket.assigned_to_name}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Dates */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Dates</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow
                                    label="Created"
                                    value={ticket.created_at}
                                />
                                <DetailRow
                                    label="Opened"
                                    value={ticket.opened_at}
                                />
                                <DetailRow
                                    label="Closed"
                                    value={ticket.closed_at}
                                />
                                <DetailRow
                                    label="Last Updated"
                                    value={ticket.updated_at}
                                />
                            </CardContent>
                        </Card>

                        {/* Activity */}
                        <ActivityLog activities={activities} />

                        {/* Danger zone */}
                        {((!ticket.is_trashed && canDelete) ||
                            (ticket.is_trashed && canDelete)) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-destructive">
                                        Danger Zone
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {!ticket.is_trashed && canDelete && (
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                            onClick={handleDelete}
                                        >
                                            <Trash2Icon data-icon="inline-start" />
                                            Move to Trash
                                        </Button>
                                    )}
                                    {ticket.is_trashed && canDelete && (
                                        <Button
                                            variant="destructive"
                                            className="w-full"
                                            onClick={handleForceDelete}
                                        >
                                            <Trash2Icon data-icon="inline-start" />
                                            Delete Permanently
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
