import { Link } from '@inertiajs/react';
import {
    CheckCircleIcon,
    Clock3Icon,
    EyeIcon,
    ListIcon,
    MailSearchIcon,
    XCircleIcon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmailLogsIndexPageProps, EmailLogListItem } from '@/types/email';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Email Logs', href: route('app.masters.email.logs.index') },
];

export default function EmailLogsIndex({
    config,
    emailLogs,
    statistics,
    providerOptions,
    templateOptions,
    filters,
}: EmailLogsIndexPageProps) {
    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search email logs...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'email_provider_id',
            value: filters.email_provider_id,
            options: [
                { value: '', label: 'All providers' },
                ...providerOptions,
            ],
        },
        {
            type: 'select',
            name: 'email_template_id',
            value: filters.email_template_id,
            options: [
                { value: '', label: 'All templates' },
                ...templateOptions,
            ],
        },
        {
            type: 'date_range',
            name: 'sent_at',
            value: filters.sent_at,
            label: 'Sent',
        },
    ];

    const statusTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: statistics.total,
            active: filters.status === 'all',
            icon: <ListIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Sent',
            value: 'sent',
            count: statistics.sent,
            active: filters.status === 'sent',
            icon: <CheckCircleIcon />,
            countVariant: 'success',
        },
        {
            label: 'Failed',
            value: 'failed',
            count: statistics.failed,
            active: filters.status === 'failed',
            icon: <XCircleIcon />,
            countVariant: 'danger',
        },
        {
            label: 'Queued',
            value: 'queued',
            count: statistics.queued,
            active: filters.status === 'queued',
            icon: <Clock3Icon />,
            countVariant: 'warning',
        },
    ];

    const columns: DatagridColumn<EmailLogListItem>[] = [
        {
            key: 'subject',
            header: 'Email',
            sortable: true,
            cell: (log) => (
                <div className="flex min-w-0 flex-col gap-1">
                    <Link
                        href={log.show_url}
                        className="truncate font-medium text-foreground hover:text-primary"
                    >
                        {log.subject}
                    </Link>
                    <span className="truncate text-sm text-muted-foreground">
                        {log.template_name || 'Manual email'}
                    </span>
                </div>
            ),
        },
        {
            key: 'provider_name',
            header: 'Provider',
            sortable: true,
        },
        {
            key: 'recipients',
            header: 'Recipients',
            cell: (log) =>
                log.recipients.length > 0
                    ? `${log.recipients.length} recipient${
                          log.recipients.length === 1 ? '' : 's'
                      }`
                    : 'No recipients',
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            type: 'badge',
            badgeVariantKey: 'status_badge',
        },
        {
            key: 'sent_at',
            header: 'Sent',
            sortable: true,
            type: 'date',
        },
    ];

    const rowActions = (log: EmailLogListItem): DatagridAction[] => [
        {
            label: 'View',
            href: log.show_url,
            icon: <EyeIcon />,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Email Logs"
            description="Inspect delivery history, recipients, and failures for outgoing mail."
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.masters.email.logs.index')}
                    rows={emailLogs}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(log) => log.id}
                    rowActions={rowActions}
                    sorting={{
                        sort: filters.sort,
                        direction: filters.direction,
                    }}
                    perPage={{
                        value: filters.per_page,
                        options: [10, 25, 50, 100],
                    }}
                    view={{
                        value: filters.view,
                        storageKey: 'email-logs-datagrid-view',
                    }}
                    renderCard={(log) => (
                        <div className="flex flex-col gap-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 space-y-1">
                                    <Link
                                        href={log.show_url}
                                        className="block truncate font-medium text-foreground hover:text-primary"
                                    >
                                        {log.subject}
                                    </Link>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {log.provider_name ||
                                            'Unknown provider'}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        (log.status_badge as React.ComponentProps<
                                            typeof Badge
                                        >['variant']) ?? 'outline'
                                    }
                                >
                                    {log.status_label}
                                </Badge>
                            </div>

                            <div className="space-y-2 text-sm text-muted-foreground">
                                <div>
                                    <span className="font-medium text-foreground">
                                        Template:{' '}
                                    </span>
                                    {log.template_name || 'Manual email'}
                                </div>
                                <div>
                                    <span className="font-medium text-foreground">
                                        Recipients:{' '}
                                    </span>
                                    {log.recipients.length > 0
                                        ? log.recipients.join(', ')
                                        : 'None'}
                                </div>
                                {log.error_message ? (
                                    <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-destructive">
                                        {log.error_message}
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    )}
                    empty={{
                        icon: <MailSearchIcon className="size-5" />,
                        title: 'No email logs found',
                        description:
                            'Email history will appear here after the application sends messages.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
