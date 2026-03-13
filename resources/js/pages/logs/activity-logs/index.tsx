import { Link, router } from '@inertiajs/react';
import {
    CalendarCheckIcon,
    CalendarIcon,
    ClockIcon,
    EyeIcon,
    HistoryIcon,
    ListIcon,
    RefreshCwIcon,
    Trash2Icon,
} from 'lucide-react';
import ActivityLogController from '@/actions/App/Http/Controllers/Logs/ActivityLogController';
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
    ActivityLogListItem,
    ActivityLogsIndexPageProps,
} from '@/types/activity-log';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Activity Logs', href: ActivityLogController.index() },
];

const EVENT_BADGE_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    create: 'default',
    created: 'default',
    stored: 'default',
    update: 'secondary',
    updated: 'secondary',
    edited: 'secondary',
    delete: 'destructive',
    deleted: 'destructive',
    trashed: 'destructive',
    force_delete: 'destructive',
    restore: 'outline',
    restored: 'outline',
};

export default function ActivityLogsIndex({
    logs,
    filters,
    statistics,
    filterOptions,
    status,
    error,
}: ActivityLogsIndexPageProps) {
    // ----- Bulk action helper -----

    const handleBulkAction = (
        action: string,
        selectedItems: ActivityLogListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedItems.length === 0) return;

        router.post(
            ActivityLogController.bulkAction().url,
            {
                action,
                ids: selectedItems.map((item) => item.id),
            },
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
            placeholder: 'Search activity logs...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'event',
            value: filters.event ?? '',
            options: [
                { value: '', label: 'All Actions' },
                ...filterOptions.event,
            ],
        },
        {
            type: 'select',
            name: 'causer_id',
            value: filters.causer_id ?? '',
            options: [
                { value: '', label: 'All Users' },
                ...filterOptions.causer_id,
            ],
        },
    ];

    // ----- Status tabs -----

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
            label: 'Trash',
            value: 'trash',
            count: statistics.trash,
            active: filters.status === 'trash',
            icon: <Trash2Icon />,
            countVariant: 'destructive',
        },
    ];

    // ----- Columns -----

    const columns: DatagridColumn<ActivityLogListItem>[] = [
        {
            key: 'event',
            header: 'Action',
            headerClassName: 'w-32',
            cellClassName: 'w-32',
            sortable: true,
            sortKey: 'event',
            cell: (log) => (
                <Badge
                    variant={
                        EVENT_BADGE_VARIANT[log.event?.toLowerCase()] ??
                        'outline'
                    }
                >
                    {log.event_label}
                </Badge>
            ),
        },
        {
            key: 'description',
            header: 'Description',
            sortable: false,
            cell: (log) => (
                <Link
                    href={log.show_url}
                    className="flex min-w-0 flex-col gap-0.5 hover:opacity-80"
                >
                    <span className="text-sm font-medium text-foreground">
                        {log.description}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {log.subject_display}
                    </span>
                </Link>
            ),
        },
        {
            key: 'causer_name',
            header: 'User',
            headerClassName: 'w-40',
            cellClassName: 'w-40',
            sortable: true,
            sortKey: 'causer_id',
            cell: (log) => (
                <span className="text-sm text-muted-foreground">
                    {log.causer_name}
                </span>
            ),
        },
        {
            key: 'created_at',
            header: 'Date',
            headerClassName: 'w-40',
            cellClassName: 'w-40',
            sortable: true,
            sortKey: 'created_at',
            cell: (log) => (
                <span className="text-xs text-muted-foreground">
                    {log.time_ago}
                </span>
            ),
        },
    ];

    // ----- Row actions -----

    const rowActions = (log: ActivityLogListItem): DatagridAction[] => {
        const actions: DatagridAction[] = [
            {
                label: 'View',
                href: log.show_url,
                icon: <EyeIcon />,
            },
        ];

        if (log.actions.restore) {
            actions.push({
                label: 'Restore',
                icon: <RefreshCwIcon />,
                href: log.actions.restore.url,
                method: 'PATCH',
                confirm: 'Restore this activity log?',
            });
        }

        if (log.actions.force_delete) {
            actions.push({
                label: 'Delete Permanently',
                icon: <Trash2Icon />,
                href: log.actions.force_delete.url,
                method: 'DELETE',
                confirm:
                    'Permanently delete this activity log? This cannot be undone.',
                variant: 'destructive',
            });
        }

        if (log.actions.delete) {
            actions.push({
                label: 'Move to Trash',
                icon: <Trash2Icon />,
                onSelect: () => {
                    if (window.confirm('Move this activity log to trash?')) {
                        router.delete(log.actions.delete.url, {
                            preserveScroll: true,
                        });
                    }
                },
                variant: 'destructive',
            });
        }

        return actions;
    };

    // ----- Bulk actions -----

    const bulkActions: DatagridBulkAction<ActivityLogListItem>[] = [
        {
            key: 'bulk-delete',
            label: 'Move to Trash',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm: 'Move selected logs to trash?',
            onSelect: (rows: ActivityLogListItem[], clear: () => void) =>
                handleBulkAction('delete', rows, clear),
        },
        {
            key: 'bulk-restore',
            label: 'Restore',
            icon: <RefreshCwIcon />,
            confirm: 'Restore selected logs?',
            onSelect: (rows: ActivityLogListItem[], clear: () => void) =>
                handleBulkAction('restore', rows, clear),
        },
        {
            key: 'bulk-force-delete',
            label: 'Delete Permanently',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm: 'Permanently delete selected logs? This cannot be undone!',
            onSelect: (rows: ActivityLogListItem[], clear: () => void) =>
                handleBulkAction('force_delete', rows, clear),
        },
    ];

    const visibleBulkActions =
        filters.status === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    // ----- Render -----

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Activity Logs"
            description="Monitor and review all system activities"
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<HistoryIcon />}
                    error={error}
                    errorIcon={<HistoryIcon />}
                />

                {/* Statistics Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total Logs"
                        value={statistics.total}
                        icon={<HistoryIcon className="size-5" />}
                        variant="primary"
                    />
                    <StatCard
                        label="Today"
                        value={statistics.today}
                        icon={<CalendarCheckIcon className="size-5" />}
                        variant="success"
                    />
                    <StatCard
                        label="This Week"
                        value={statistics.this_week}
                        icon={<CalendarIcon className="size-5" />}
                        variant="info"
                    />
                    <StatCard
                        label="This Month"
                        value={statistics.this_month}
                        icon={<ClockIcon className="size-5" />}
                        variant="warning"
                    />
                </div>

                <Datagrid
                    action={ActivityLogController.index().url}
                    rows={logs}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{
                        name: 'status',
                        items: statusTabs,
                    }}
                    getRowKey={(log) => log.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
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
                        storageKey: 'activity-logs-datagrid-view',
                    }}
                    renderCard={(log) => (
                        <div className="flex flex-col gap-3">
                            <Link
                                href={log.show_url}
                                className="flex flex-col gap-1 hover:opacity-80"
                            >
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            EVENT_BADGE_VARIANT[
                                                log.event?.toLowerCase()
                                            ] ?? 'outline'
                                        }
                                    >
                                        {log.event_label}
                                    </Badge>
                                </div>
                                <span className="mt-1 text-sm font-medium text-foreground">
                                    {log.description}
                                </span>
                            </Link>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        User
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {log.causer_name}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Subject
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {log.subject_display}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        When
                                    </div>
                                    <div className="mt-1 text-sm text-muted-foreground">
                                        {log.time_ago}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    submitLabel="Filters"
                    submitButtonVariant="outline"
                    empty={{
                        icon: <HistoryIcon />,
                        title: 'No activity logs found',
                        description:
                            'Activity logs will appear here once actions are performed.',
                    }}
                />
            </div>
        </AppLayout>
    );
}

// =========================================================================
// HELPER COMPONENTS
// =========================================================================

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
