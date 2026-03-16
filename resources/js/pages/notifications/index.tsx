import { Link, router } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    BellIcon,
    BellOffIcon,
    CheckCheckIcon,
    InboxIcon,
    MailIcon,
    MailOpenIcon,
    Settings2Icon,
    Trash2Icon,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    NotificationListItem,
    NotificationsIndexPageProps,
} from '@/types/notification';
import type { BadgeVariant } from '@/types/ui';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Notifications', href: route('app.notifications.index') },
];

const PRIORITY_VARIANT: Record<string, BadgeVariant> = {
    high: 'danger',
    medium: 'warning',
    low: 'secondary',
};

const CATEGORY_VARIANT: Record<string, BadgeVariant> = {
    system: 'danger',
    website: 'info',
    user: 'success',
    cms: 'outline',
    broadcast: 'secondary',
};

type NotificationsActionDialog = 'mark-all-read' | 'delete-read' | null;

function formatNotificationDate(value: string): string {
    return new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function SummaryCard({
    label,
    value,
    icon,
    variant = 'secondary',
}: {
    label: string;
    value: number;
    icon: ReactNode;
    variant?: BadgeVariant;
}) {
    return (
        <div className="flex items-center gap-3 rounded-xl border bg-muted/20 px-4 py-3">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl border bg-background">
                {icon}
            </div>
            <div className="min-w-0">
                <p className="text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase">
                    {label}
                </p>
                <div className="mt-1 flex items-center gap-2">
                    <span className="text-xl font-semibold text-foreground">
                        {value.toLocaleString()}
                    </span>
                    <Badge variant={variant}>{label}</Badge>
                </div>
            </div>
        </div>
    );
}

export default function NotificationsIndex({
    notifications,
    stats,
    filters,
    categoryOptions,
    priorityOptions,
    status,
    error,
}: NotificationsIndexPageProps) {
    const handleMarkMultipleAsRead = (
        selectedItems: NotificationListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedItems.length === 0) {
            return;
        }

        router.post(
            route('app.notifications.mark-multiple-read'),
            { ids: selectedItems.map((item) => item.id) },
            {
                preserveScroll: true,
                onSuccess: () => clearSelection(),
            },
        );
    };

    const handleDeleteMultiple = (
        selectedItems: NotificationListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedItems.length === 0) {
            return;
        }

        router.post(
            route('app.notifications.delete-multiple'),
            { ids: selectedItems.map((item) => item.id) },
            {
                preserveScroll: true,
                onSuccess: () => clearSelection(),
            },
        );
    };

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search notifications...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'category',
            value: filters.category ?? '',
            options: [
                { value: '', label: 'All Categories' },
                ...categoryOptions,
            ],
        },
        {
            type: 'select',
            name: 'priority',
            value: filters.priority ?? '',
            options: [
                { value: '', label: 'All Priorities' },
                ...priorityOptions,
            ],
        },
    ];

    const statusTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: stats.total,
            active: !filters.filter || filters.filter === 'all',
            icon: <InboxIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Unread',
            value: 'unread',
            count: stats.unread,
            active: filters.filter === 'unread',
            icon: <MailIcon />,
            countVariant: 'info',
        },
        {
            label: 'Read',
            value: 'read',
            count: stats.read,
            active: filters.filter === 'read',
            icon: <MailOpenIcon />,
            countVariant: 'outline',
        },
    ];

    const columns: DatagridColumn<NotificationListItem>[] = [
        {
            key: 'priority',
            header: 'Priority',
            headerClassName: 'w-28',
            cellClassName: 'w-28',
            sortable: false,
            cell: (notification) => (
                <Badge
                    variant={
                        PRIORITY_VARIANT[notification.priority] ?? 'outline'
                    }
                >
                    {notification.priority_label}
                </Badge>
            ),
        },
        {
            key: 'title_text',
            header: 'Notification',
            sortable: false,
            cell: (notification) => (
                <div className="flex min-w-0 flex-col gap-0.5">
                    <span
                        className={cn(
                            'text-sm',
                            notification.is_read
                                ? 'text-muted-foreground'
                                : 'font-medium text-foreground',
                        )}
                    >
                        {notification.title_text}
                    </span>
                    {notification.sanitized_message ? (
                        <span className="line-clamp-1 text-xs text-muted-foreground">
                            {notification.sanitized_message}
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'category',
            header: 'Category',
            headerClassName: 'w-32',
            cellClassName: 'w-32',
            sortable: false,
            cell: (notification) => (
                <Badge
                    variant={
                        CATEGORY_VARIANT[notification.category] ?? 'outline'
                    }
                >
                    {notification.category_label}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            header: 'Received',
            headerClassName: 'w-40',
            cellClassName: 'w-40',
            sortable: false,
            cell: (notification) => (
                <span className="text-xs text-muted-foreground">
                    {formatNotificationDate(notification.created_at)}
                </span>
            ),
        },
    ];

    const rowActions = (
        notification: NotificationListItem,
    ): DatagridAction[] => {
        const actions: DatagridAction[] = [];

        if (notification.url) {
            actions.push({
                label: 'Open',
                href: route('app.notifications.show', notification.id),
                icon: <MailOpenIcon />,
            });
        }

        if (notification.is_read) {
            actions.push({
                label: 'Mark as Unread',
                icon: <MailIcon />,
                onSelect: () => {
                    router.post(
                        route('app.notifications.mark-unread', notification.id),
                        {},
                        { preserveScroll: true },
                    );
                },
            });
        } else {
            actions.push({
                label: 'Mark as Read',
                icon: <MailOpenIcon />,
                onSelect: () => {
                    router.post(
                        route('app.notifications.mark-read', notification.id),
                        {},
                        { preserveScroll: true },
                    );
                },
            });
        }

        actions.push({
            label: 'Delete',
            icon: <Trash2Icon />,
            variant: 'destructive',
            confirm: 'Delete this notification permanently?',
            href: route('app.notifications.destroy', notification.id),
            method: 'DELETE',
        });

        return actions;
    };

    const bulkActions: DatagridBulkAction<NotificationListItem>[] = [
        {
            key: 'bulk-mark-read',
            label: 'Mark as Read',
            icon: <CheckCheckIcon />,
            confirm: 'Mark selected notifications as read?',
            onSelect: (rows, clear) => handleMarkMultipleAsRead(rows, clear),
        },
        {
            key: 'bulk-delete',
            label: 'Delete',
            icon: <Trash2Icon />,
            variant: 'destructive',
            confirm: 'Delete selected notifications?',
            onSelect: (rows, clear) => handleDeleteMultiple(rows, clear),
        },
    ];

    const [dialog, setDialog] = useState<NotificationsActionDialog>(null);

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Notifications"
            description="Review alerts, activity, and messages from one inbox."
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('app.notifications.preferences')}>
                        <Settings2Icon data-icon="inline-start" />
                        Preferences
                    </Link>
                </Button>
            }
        >
            <div className="grid gap-6 xl:grid-cols-[248px_minmax(0,1fr)] 2xl:grid-cols-[260px_minmax(0,1fr)]">
                <div className="flex flex-col gap-6">
                    <Card className="py-6">
                        <CardHeader className="gap-4 px-6">
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex size-11 items-center justify-center rounded-2xl border bg-muted/30">
                                    <BellIcon className="size-5 text-foreground" />
                                </div>
                                <Badge
                                    variant={
                                        stats.unread > 0
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {stats.unread > 0
                                        ? `${stats.unread} unread`
                                        : 'All caught up'}
                                </Badge>
                            </div>
                            <div className="space-y-1.5">
                                <CardTitle className="text-xl font-semibold">
                                    Notification center
                                </CardTitle>
                                <CardDescription className="text-sm leading-6">
                                    Stay on top of alerts and account activity
                                    from one place.
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3 px-6">
                            <SummaryCard
                                label="Total"
                                value={stats.total}
                                icon={<InboxIcon className="size-4.5" />}
                            />
                            <SummaryCard
                                label="Unread"
                                value={stats.unread}
                                icon={<MailIcon className="size-4.5" />}
                                variant="default"
                            />
                            <SummaryCard
                                label="High priority"
                                value={stats.high_priority}
                                icon={
                                    <AlertTriangleIcon className="size-4.5" />
                                }
                                variant="destructive"
                            />
                        </CardContent>
                    </Card>

                    <Card className="py-6">
                        <CardHeader className="gap-2">
                            <CardTitle className="text-[1.05rem] font-semibold">
                                Quick actions
                            </CardTitle>
                            <CardDescription className="text-sm leading-6">
                                Use these when you want to clean up your inbox
                                without opening individual notifications.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Button
                                type="button"
                                className="w-full"
                                disabled={stats.unread === 0}
                                onClick={() => setDialog('mark-all-read')}
                            >
                                <CheckCheckIcon data-icon="inline-start" />
                                Mark all as read
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                disabled={stats.read === 0}
                                onClick={() => setDialog('delete-read')}
                            >
                                <Trash2Icon data-icon="inline-start" />
                                Delete read notifications
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <ResourceFeedbackAlerts
                        status={status}
                        statusTitle="Notification updated"
                        statusIcon={<BellIcon />}
                        error={error}
                        errorTitle="Notification action failed"
                        errorIcon={<BellOffIcon />}
                    />

                    <Datagrid
                        action={route('app.notifications.index')}
                        rows={notifications}
                        columns={columns}
                        filters={gridFilters}
                        tabs={{
                            name: 'filter',
                            items: statusTabs,
                        }}
                        getRowKey={(notification) => notification.id}
                        rowActions={rowActions}
                        bulkActions={bulkActions}
                        perPage={{
                            value: 10,
                            options: [10, 25, 50],
                        }}
                        empty={{
                            icon: <InboxIcon />,
                            title: 'No notifications found',
                            description:
                                'Try a different search or filter, or come back later when new activity arrives.',
                        }}
                        title="Inbox"
                        description="Search, filter, and act on notifications without leaving the page."
                        summary={`${stats.total.toLocaleString()} total notifications · ${stats.unread.toLocaleString()} unread`}
                        renderCardHeader={(notification) => (
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            PRIORITY_VARIANT[
                                                notification.priority
                                            ] ?? 'outline'
                                        }
                                    >
                                        {notification.priority_label}
                                    </Badge>
                                    <Badge
                                        variant={
                                            CATEGORY_VARIANT[
                                                notification.category
                                            ] ?? 'outline'
                                        }
                                    >
                                        {notification.category_label}
                                    </Badge>
                                </div>
                                {!notification.is_read ? (
                                    <span className="size-2 rounded-full bg-primary" />
                                ) : null}
                            </div>
                        )}
                        renderCard={(notification) => (
                            <div className="flex flex-col gap-3">
                                <div className="space-y-1">
                                    <span
                                        className={cn(
                                            'text-sm',
                                            notification.is_read
                                                ? 'text-muted-foreground'
                                                : 'font-medium text-foreground',
                                        )}
                                    >
                                        {notification.title_text}
                                    </span>
                                    {notification.sanitized_message ? (
                                        <p className="line-clamp-3 text-xs leading-5 text-muted-foreground">
                                            {notification.sanitized_message}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {formatNotificationDate(
                                        notification.created_at,
                                    )}
                                </div>
                            </div>
                        )}
                        cardGridClassName="grid-cols-1"
                    />
                </div>
            </div>

            <ConfirmationDialog
                open={dialog !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDialog(null);
                    }
                }}
                title={
                    dialog === 'delete-read'
                        ? 'Delete read notifications'
                        : 'Mark all as read'
                }
                description={
                    dialog === 'delete-read'
                        ? 'This removes only notifications you have already read. Unread notifications stay in your inbox.'
                        : 'Unread notifications will move into your read archive so your inbox looks clean again.'
                }
                confirmLabel={
                    dialog === 'delete-read'
                        ? 'Delete read notifications'
                        : 'Mark all as read'
                }
                icon={
                    dialog === 'delete-read' ? (
                        <Trash2Icon className="size-4.5" />
                    ) : (
                        <CheckCheckIcon className="size-4.5" />
                    )
                }
                tone={dialog === 'delete-read' ? 'destructive' : 'default'}
                onConfirm={() => {
                    if (dialog === 'delete-read') {
                        router.delete(
                            route('app.notifications.delete-all-read'),
                            {
                                preserveScroll: true,
                            },
                        );
                    }

                    if (dialog === 'mark-all-read') {
                        router.post(
                            route('app.notifications.mark-all-read'),
                            {},
                            {
                                preserveScroll: true,
                            },
                        );
                    }

                    setDialog(null);
                }}
                confirmClassName={
                    dialog === 'delete-read'
                        ? 'bg-destructive text-white hover:bg-destructive/90'
                        : undefined
                }
            />
        </AppLayout>
    );
}
