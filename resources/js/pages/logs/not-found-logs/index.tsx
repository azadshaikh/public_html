import { Link, router } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    BotIcon,
    EyeIcon,
    FileQuestion,
    ListIcon,
    RefreshCwIcon,
    SearchXIcon,
    Trash2Icon,
    UserIcon,
} from 'lucide-react';
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
import type { BreadcrumbItem } from '@/types';
import type {
    NotFoundLogListItem,
    NotFoundLogsIndexPageProps,
} from '@/types/not-found-log';
import type { BadgeVariant } from '@/types/ui';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: '404 Logs', href: route('app.logs.not-found-logs.index') },
];

const STATUS_BADGE_VARIANT: Record<string, BadgeVariant> = {
    suspicious: 'danger',
    bot: 'warning',
    human: 'success',
};

export default function NotFoundLogsIndex({
    notFoundLogs,
    filters,
    statistics,
    status,
    error,
}: NotFoundLogsIndexPageProps) {
    // ----- Bulk action helper -----

    const handleBulkAction = (
        action: string,
        selectedItems: NotFoundLogListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedItems.length === 0) return;

        router.post(
            route('app.logs.not-found-logs.bulk-action'),
            {
                action,
                ids: selectedItems.map((item) => item.id),
                status: filters.status,
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
            placeholder: 'Search by URL or IP...',
            className: 'lg:min-w-80',
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
            label: 'Suspicious',
            value: 'suspicious',
            count: statistics.suspicious,
            active: filters.status === 'suspicious',
            icon: <AlertTriangleIcon />,
            countVariant: 'danger',
        },
        {
            label: 'Bots',
            value: 'bots',
            count: statistics.bots,
            active: filters.status === 'bots',
            icon: <BotIcon />,
            countVariant: 'warning',
        },
        {
            label: 'Human',
            value: 'human',
            count: statistics.human,
            active: filters.status === 'human',
            icon: <UserIcon />,
            countVariant: 'success',
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

    const columns: DatagridColumn<NotFoundLogListItem>[] = [
        {
            key: 'status_badge',
            header: 'Type',
            headerClassName: 'w-28',
            cellClassName: 'w-28',
            sortable: false,
            cell: (log) => (
                <Badge
                    variant={
                        STATUS_BADGE_VARIANT[log.status_badge] ?? 'outline'
                    }
                >
                    {log.status_badge_label}
                </Badge>
            ),
        },
        {
            key: 'url',
            header: 'URL',
            sortable: true,
            sortKey: 'url',
            cell: (log) => (
                <Link
                    href={log.show_url}
                    className="font-medium text-foreground hover:opacity-80"
                >
                    <span className="line-clamp-1">{log.url_display}</span>
                </Link>
            ),
        },
        {
            key: 'referer',
            header: 'Referer',
            headerClassName: 'w-44',
            cellClassName: 'w-44',
            sortable: false,
            cell: (log) => (
                <span className="line-clamp-1 text-xs text-muted-foreground">
                    {log.referer_display || '—'}
                </span>
            ),
        },
        {
            key: 'ip_address',
            header: 'IP Address',
            headerClassName: 'w-36',
            cellClassName: 'w-36',
            sortable: true,
            sortKey: 'ip_address',
            cell: (log) => (
                <span className="font-mono text-sm">{log.ip_address}</span>
            ),
        },
        {
            key: 'method',
            header: 'Method',
            headerClassName: 'w-20',
            cellClassName: 'w-20',
            sortable: false,
            cell: (log) => (
                <Badge variant="outline" className="font-mono text-xs">
                    {log.method}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            header: 'Date',
            headerClassName: 'w-36',
            cellClassName: 'w-36',
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

    const rowActions = (log: NotFoundLogListItem): DatagridAction[] => {
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
                confirm: 'Restore this log entry?',
            });
        }

        if (log.actions.force_delete) {
            actions.push({
                label: 'Delete Permanently',
                icon: <Trash2Icon />,
                href: log.actions.force_delete.url,
                method: 'DELETE',
                confirm:
                    'Permanently delete this log entry? This cannot be undone.',
                variant: 'destructive',
            });
        }

        if (log.actions.delete) {
            actions.push({
                label: 'Move to Trash',
                icon: <Trash2Icon />,
                onSelect: () => {
                    if (window.confirm('Move this log entry to trash?')) {
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

    const bulkActions: DatagridBulkAction<NotFoundLogListItem>[] = [
        {
            key: 'bulk-delete',
            label: 'Move to Trash',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm: 'Move selected logs to trash?',
            onSelect: (rows: NotFoundLogListItem[], clear: () => void) =>
                handleBulkAction('delete', rows, clear),
        },
        {
            key: 'bulk-restore',
            label: 'Restore',
            icon: <RefreshCwIcon />,
            confirm: 'Restore selected logs?',
            onSelect: (rows: NotFoundLogListItem[], clear: () => void) =>
                handleBulkAction('restore', rows, clear),
        },
        {
            key: 'bulk-force-delete',
            label: 'Delete Permanently',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm: 'Permanently delete selected logs? This cannot be undone!',
            onSelect: (rows: NotFoundLogListItem[], clear: () => void) =>
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
            title="404 Logs"
            description="Monitor page-not-found errors and suspicious requests"
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<SearchXIcon />}
                    error={error}
                    errorIcon={<SearchXIcon />}
                />

                {/* Statistics Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total"
                        value={statistics.total}
                        icon={<FileQuestion className="size-5" />}
                        variant="primary"
                    />
                    <StatCard
                        label="Suspicious"
                        value={statistics.suspicious}
                        icon={<AlertTriangleIcon className="size-5" />}
                        variant="danger"
                    />
                    <StatCard
                        label="Bots"
                        value={statistics.bots}
                        icon={<BotIcon className="size-5" />}
                        variant="warning"
                    />
                    <StatCard
                        label="Human"
                        value={statistics.human}
                        icon={<UserIcon className="size-5" />}
                        variant="success"
                    />
                </div>

                <Datagrid
                    action={route('app.logs.not-found-logs.index')}
                    rows={notFoundLogs}
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
                        storageKey: 'not-found-logs-datagrid-view',
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
                                            STATUS_BADGE_VARIANT[
                                                log.status_badge
                                            ] ?? 'outline'
                                        }
                                    >
                                        {log.status_badge_label}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        className="font-mono text-xs"
                                    >
                                        {log.method}
                                    </Badge>
                                </div>
                                <span className="mt-1 line-clamp-1 text-sm font-medium text-foreground">
                                    {log.url_display}
                                </span>
                            </Link>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        IP Address
                                    </div>
                                    <div className="mt-1 font-mono text-sm text-foreground">
                                        {log.ip_address}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Referer
                                    </div>
                                    <div className="mt-1 line-clamp-1 text-sm text-foreground">
                                        {log.referer_display || '—'}
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
                        icon: <SearchXIcon />,
                        title: 'No 404 logs found',
                        description:
                            'Page-not-found errors will appear here once they occur.',
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
