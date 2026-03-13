import { router } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    BellIcon,
    BellOffIcon,
    CheckCheckIcon,
    InboxIcon,
    MailIcon,
    MailOpenIcon,
    Trash2Icon,
} from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
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
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import type {
    NotificationListItem,
    NotificationsIndexPageProps,
} from '@/types/notification';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Notifications', href: NotificationController.index() },
];

const PRIORITY_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    high: 'destructive',
    medium: 'secondary',
    low: 'outline',
};

const CATEGORY_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    system: 'destructive',
    website: 'default',
    user: 'secondary',
    cms: 'outline',
    broadcast: 'secondary',
};

export default function NotificationsIndex({
    notifications,
    stats,
    filters,
    categoryOptions,
    priorityOptions,
    status,
    error,
}: NotificationsIndexPageProps) {
    // ----- Bulk action helpers -----

    const handleMarkMultipleAsRead = (
        selectedItems: NotificationListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedItems.length === 0) return;

        router.post(
            NotificationController.markMultipleAsRead().url,
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
        if (selectedItems.length === 0) return;

        router.post(
            NotificationController.deleteMultiple().url,
            { ids: selectedItems.map((item) => item.id) },
            {
                preserveScroll: true,
                onSuccess: () => clearSelection(),
            },
        );
    };

    // ----- Filters -----

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

    // ----- Status tabs -----

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
            countVariant: 'default',
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

    // ----- Columns -----

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
                        className={`text-sm ${notification.is_read ? 'text-muted-foreground' : 'font-medium text-foreground'}`}
                    >
                        {notification.title_text}
                    </span>
                    {notification.sanitized_message && (
                        <span className="line-clamp-1 text-xs text-muted-foreground">
                            {notification.sanitized_message}
                        </span>
                    )}
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
            header: 'Date',
            headerClassName: 'w-40',
            cellClassName: 'w-40',
            sortable: false,
            cell: (notification) => (
                <span className="text-xs text-muted-foreground">
                    {new Date(notification.created_at).toLocaleDateString(
                        undefined,
                        {
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                        },
                    )}
                </span>
            ),
        },
    ];

    // ----- Row actions -----

    const rowActions = (
        notification: NotificationListItem,
    ): DatagridAction[] => {
        const actions: DatagridAction[] = [];

        if (notification.url) {
            actions.push({
                label: 'View',
                href: NotificationController.show(notification.id).url,
                icon: <MailOpenIcon />,
            });
        }

        if (notification.is_read) {
            actions.push({
                label: 'Mark as Unread',
                icon: <MailIcon />,
                onSelect: () => {
                    router.post(
                        NotificationController.markAsUnread(notification.id)
                            .url,
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
                        NotificationController.markAsRead(notification.id).url,
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
            onSelect: () => {
                if (window.confirm('Delete this notification?')) {
                    router.delete(
                        NotificationController.destroy(notification.id).url,
                        { preserveScroll: true },
                    );
                }
            },
        });

        return actions;
    };

    // ----- Bulk actions -----

    const bulkActions: DatagridBulkAction<NotificationListItem>[] = [
        {
            key: 'bulk-mark-read',
            label: 'Mark as Read',
            icon: <CheckCheckIcon />,
            confirm: 'Mark selected notifications as read?',
            onSelect: (rows: NotificationListItem[], clear: () => void) =>
                handleMarkMultipleAsRead(rows, clear),
        },
        {
            key: 'bulk-delete',
            label: 'Delete',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm: 'Delete selected notifications?',
            onSelect: (rows: NotificationListItem[], clear: () => void) =>
                handleDeleteMultiple(rows, clear),
        },
    ];

    // ----- Render -----

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Notifications"
            description="View and manage your notifications"
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<BellIcon />}
                    error={error}
                    errorIcon={<BellOffIcon />}
                />

                {/* Statistics Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total"
                        value={stats.total}
                        icon={<InboxIcon className="size-5" />}
                        variant="primary"
                    />
                    <StatCard
                        label="Unread"
                        value={stats.unread}
                        icon={<MailIcon className="size-5" />}
                        variant="info"
                    />
                    <StatCard
                        label="Read"
                        value={stats.read}
                        icon={<MailOpenIcon className="size-5" />}
                        variant="success"
                    />
                    <StatCard
                        label="High Priority"
                        value={stats.high_priority}
                        icon={<AlertTriangleIcon className="size-5" />}
                        variant="danger"
                    />
                </div>

                <Datagrid
                    action={NotificationController.index().url}
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
                    renderCard={(notification) => (
                        <div className="flex flex-col gap-3">
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
                                {!notification.is_read && (
                                    <span className="size-2 rounded-full bg-primary" />
                                )}
                            </div>
                            <div>
                                <span
                                    className={`text-sm ${notification.is_read ? 'text-muted-foreground' : 'font-medium text-foreground'}`}
                                >
                                    {notification.title_text}
                                </span>
                                {notification.sanitized_message && (
                                    <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                        {notification.sanitized_message}
                                    </p>
                                )}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {new Date(
                                    notification.created_at,
                                ).toLocaleDateString(undefined, {
                                    month: 'short',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                })}
                            </div>
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}

// ----- StatCard -----

const VARIANT_CLASSES: Record<string, string> = {
    primary: 'bg-primary/10 text-primary',
    success: 'bg-green-500/10 text-green-600 dark:text-green-400',
    info: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    danger: 'bg-red-500/10 text-red-600 dark:text-red-400',
};

function StatCard({
    label,
    value,
    icon,
    variant = 'primary',
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    variant?: string;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4 py-4">
                <div
                    className={`flex size-10 items-center justify-center rounded-lg ${VARIANT_CLASSES[variant] ?? VARIANT_CLASSES.primary}`}
                >
                    {icon}
                </div>
                <div>
                    <div className="text-sm font-medium text-muted-foreground">
                        {label}
                    </div>
                    <div className="text-2xl font-bold text-foreground">
                        {value.toLocaleString()}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
