import { Link, router } from '@inertiajs/react';
import {
    BanIcon,
    CheckCircleIcon,
    EyeIcon,
    ListIcon,
    RefreshCwIcon,
    ShieldIcon,
    Trash2Icon,
    XCircleIcon,
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
import type { BadgeVariant } from '@/types/ui';
import type {
    LoginAttemptListItem,
    LoginAttemptsIndexPageProps,
} from '@/types/login-attempt';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Login Attempts', href: route('app.logs.login-attempts.index') },
];

const STATUS_BADGE_VARIANT: Record<string, BadgeVariant> = {
    success: 'success',
    failed: 'danger',
    blocked: 'warning',
    cleared: 'secondary',
};

export default function LoginAttemptsIndex({
    loginAttempts,
    filters,
    statistics,
    status,
    error,
}: LoginAttemptsIndexPageProps) {
    // ----- Bulk action helper -----

    const handleBulkAction = (
        action: string,
        selectedItems: LoginAttemptListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedItems.length === 0) return;

        router.post(
            route('app.logs.login-attempts.bulk-action'),
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
            placeholder: 'Search by email or IP...',
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
            label: 'Successful',
            value: 'success',
            count: statistics.success,
            active: filters.status === 'success',
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
            label: 'Blocked',
            value: 'blocked',
            count: statistics.blocked,
            active: filters.status === 'blocked',
            icon: <BanIcon />,
            countVariant: 'warning',
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

    const columns: DatagridColumn<LoginAttemptListItem>[] = [
        {
            key: 'status',
            header: 'Status',
            headerClassName: 'w-28',
            cellClassName: 'w-28',
            sortable: true,
            sortKey: 'status',
            cell: (attempt) => (
                <Badge
                    variant={STATUS_BADGE_VARIANT[attempt.status] ?? 'outline'}
                >
                    {attempt.status_label}
                </Badge>
            ),
        },
        {
            key: 'email',
            header: 'Email',
            sortable: true,
            sortKey: 'email',
            cell: (attempt) => (
                <Link
                    href={attempt.show_url}
                    className="font-medium text-foreground hover:opacity-80"
                >
                    {attempt.email}
                </Link>
            ),
        },
        {
            key: 'ip_address',
            header: 'IP Address',
            headerClassName: 'w-40',
            cellClassName: 'w-40',
            sortable: true,
            sortKey: 'ip_address',
            cell: (attempt) => (
                <span className="font-mono text-sm">{attempt.ip_address}</span>
            ),
        },
        {
            key: 'failure_reason',
            header: 'Reason',
            headerClassName: 'w-36',
            cellClassName: 'w-36',
            sortable: false,
            cell: (attempt) => (
                <span className="text-xs text-muted-foreground">
                    {attempt.failure_reason_label}
                </span>
            ),
        },
        {
            key: 'browser',
            header: 'Browser',
            headerClassName: 'w-24',
            cellClassName: 'w-24',
            sortable: false,
            cell: (attempt) => (
                <span className="text-xs text-muted-foreground">
                    {attempt.browser}
                </span>
            ),
        },
        {
            key: 'created_at',
            header: 'Date',
            headerClassName: 'w-36',
            cellClassName: 'w-36',
            sortable: true,
            sortKey: 'created_at',
            cell: (attempt) => (
                <span className="text-xs text-muted-foreground">
                    {attempt.time_ago}
                </span>
            ),
        },
    ];

    // ----- Row actions -----

    const rowActions = (attempt: LoginAttemptListItem): DatagridAction[] => {
        const actions: DatagridAction[] = [
            {
                label: 'View',
                href: attempt.show_url,
                icon: <EyeIcon />,
            },
        ];

        if (attempt.actions.restore) {
            actions.push({
                label: 'Restore',
                icon: <RefreshCwIcon />,
                href: attempt.actions.restore.url,
                method: 'PATCH',
                confirm: 'Restore this login attempt?',
            });
        }

        if (attempt.actions.force_delete) {
            actions.push({
                label: 'Delete Permanently',
                icon: <Trash2Icon />,
                href: attempt.actions.force_delete.url,
                method: 'DELETE',
                confirm:
                    'Permanently delete this login attempt? This cannot be undone.',
                variant: 'destructive',
            });
        }

        if (attempt.actions.delete) {
            actions.push({
                label: 'Move to Trash',
                icon: <Trash2Icon />,
                onSelect: () => {
                    if (window.confirm('Move this login attempt to trash?')) {
                        router.delete(attempt.actions.delete.url, {
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

    const bulkActions: DatagridBulkAction<LoginAttemptListItem>[] = [
        {
            key: 'bulk-delete',
            label: 'Move to Trash',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm: 'Move selected attempts to trash?',
            onSelect: (rows: LoginAttemptListItem[], clear: () => void) =>
                handleBulkAction('delete', rows, clear),
        },
        {
            key: 'bulk-restore',
            label: 'Restore',
            icon: <RefreshCwIcon />,
            confirm: 'Restore selected attempts?',
            onSelect: (rows: LoginAttemptListItem[], clear: () => void) =>
                handleBulkAction('restore', rows, clear),
        },
        {
            key: 'bulk-force-delete',
            label: 'Delete Permanently',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm:
                'Permanently delete selected attempts? This cannot be undone!',
            onSelect: (rows: LoginAttemptListItem[], clear: () => void) =>
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
            title="Login Attempts"
            description="Monitor and review all login attempts"
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<ShieldIcon />}
                    error={error}
                    errorIcon={<ShieldIcon />}
                />

                {/* Statistics Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total"
                        value={statistics.total}
                        icon={<ShieldIcon className="size-5" />}
                        variant="primary"
                    />
                    <StatCard
                        label="Successful"
                        value={statistics.success}
                        icon={<CheckCircleIcon className="size-5" />}
                        variant="success"
                    />
                    <StatCard
                        label="Failed"
                        value={statistics.failed}
                        icon={<XCircleIcon className="size-5" />}
                        variant="danger"
                    />
                    <StatCard
                        label="Blocked"
                        value={statistics.blocked}
                        icon={<BanIcon className="size-5" />}
                        variant="warning"
                    />
                </div>

                <Datagrid
                    action={route('app.logs.login-attempts.index')}
                    rows={loginAttempts}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{
                        name: 'status',
                        items: statusTabs,
                    }}
                    getRowKey={(attempt) => attempt.id}
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
                        storageKey: 'login-attempts-datagrid-view',
                    }}
                    renderCard={(attempt) => (
                        <div className="flex flex-col gap-3">
                            <Link
                                href={attempt.show_url}
                                className="flex flex-col gap-1 hover:opacity-80"
                            >
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            STATUS_BADGE_VARIANT[
                                                attempt.status
                                            ] ?? 'outline'
                                        }
                                    >
                                        {attempt.status_label}
                                    </Badge>
                                    <span className="text-sm font-medium text-foreground">
                                        {attempt.email}
                                    </span>
                                </div>
                            </Link>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        IP Address
                                    </div>
                                    <div className="mt-1 font-mono text-sm text-foreground">
                                        {attempt.ip_address}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Browser
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {attempt.browser}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        When
                                    </div>
                                    <div className="mt-1 text-sm text-muted-foreground">
                                        {attempt.time_ago}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    submitLabel="Filters"
                    submitButtonVariant="outline"
                    empty={{
                        icon: <ShieldIcon />,
                        title: 'No login attempts found',
                        description:
                            'Login attempts will appear here once users try to log in.',
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
