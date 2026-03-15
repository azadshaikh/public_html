import { router } from '@inertiajs/react';
import {
    ActivityIcon,
    AlertTriangleIcon,
    BarChart3Icon,
    CheckCircle2Icon,
    Clock3Icon,
    CopyIcon,
    PauseCircleIcon,
    PlayCircleIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    SearchXIcon,
    ServerCogIcon,
    ShieldAlertIcon,
    Trash2Icon,
    WorkflowIcon,
    XCircleIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { Line, LineChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    QueueMonitorChartData,
    QueueMonitorIndexPageProps,
    QueueMonitorListItem,
    QueueMonitorMetric,
    QueueMonitorRowAction,
    QueueWorkerStats,
} from '@/types/queue-monitor';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Queue Monitor',
        href: route('app.masters.queue-monitor.index'),
    },
];

const STATUS_VARIANTS = {
    running: 'info',
    succeeded: 'success',
    failed: 'danger',
    stale: 'warning',
    queued: 'secondary',
} as const;

const CHART_PALETTE = [
    '#0f766e',
    '#2563eb',
    '#f59e0b',
    '#dc2626',
    '#7c3aed',
    '#db2777',
    '#0891b2',
    '#16a34a',
];

export default function QueueMonitorIndex({
    monitors,
    statistics,
    filters,
    metrics,
    queueStats,
    chartData,
    workerStats,
    queueOptions,
    ui,
}: QueueMonitorIndexPageProps) {
    const [paused, setPaused] = useState(false);
    const [lastUpdatedAt, setLastUpdatedAt] = useState(() => Date.now());
    const [clockNow, setClockNow] = useState(() => Date.now());
    const [liveWorkerStats, setLiveWorkerStats] = useState(workerStats);
    const [workersLoading, setWorkersLoading] = useState(false);
    const [selectedException, setSelectedException] = useState<string | null>(
        null,
    );

    useEffect(() => {
        setLiveWorkerStats(workerStats);
    }, [workerStats]);

    useEffect(() => {
        const intervalId = window.setInterval(() => {
            setClockNow(Date.now());
        }, 5000);

        return () => window.clearInterval(intervalId);
    }, []);

    useEffect(() => {
        if (!ui.refreshInterval || paused) {
            return;
        }

        const intervalId = window.setInterval(() => {
            router.reload({
                only: [
                    'monitors',
                    'statistics',
                    'metrics',
                    'queueStats',
                    'chartData',
                    'workerStats',
                    'queueOptions',
                ],
                onSuccess: () => {
                    setLastUpdatedAt(Date.now());
                },
            });
        }, ui.refreshInterval * 1000);

        return () => window.clearInterval(intervalId);
    }, [paused, ui.refreshInterval]);

    useEffect(() => {
        if (
            !ui.workerRefreshInterval ||
            paused ||
            (workerStats === null && liveWorkerStats === null)
        ) {
            return;
        }

        const intervalId = window.setInterval(async () => {
            setWorkersLoading(true);

            try {
                const response = await fetch(
                    route('app.masters.queue-monitor.workers'),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    return;
                }

                const payload = (await response.json()) as QueueWorkerStats;
                setLiveWorkerStats(payload);
            } finally {
                setWorkersLoading(false);
            }
        }, ui.workerRefreshInterval * 1000);

        return () => window.clearInterval(intervalId);
    }, [liveWorkerStats, paused, ui.workerRefreshInterval, workerStats]);

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search jobs, queues, and errors...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'queue',
            value: filters.queue,
            options: [{ value: '', label: 'All queues' }, ...queueOptions],
        },
        {
            type: 'search',
            name: 'metadata_key',
            value: filters.metadata_key,
            placeholder: 'Metadata key',
        },
        {
            type: 'search',
            name: 'metadata_value',
            value: filters.metadata_value,
            placeholder: 'Metadata value',
        },
    ];

    const statusTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: statistics.total,
            active: filters.status === 'all',
            icon: <WorkflowIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Succeeded',
            value: 'succeeded',
            count: statistics.succeeded,
            active: filters.status === 'succeeded',
            icon: <CheckCircle2Icon />,
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
            label: 'Running',
            value: 'running',
            count: statistics.running,
            active: filters.status === 'running',
            icon: <ActivityIcon />,
            countVariant: 'info',
        },
        {
            label: 'Queued',
            value: 'queued',
            count: statistics.queued,
            active: filters.status === 'queued',
            icon: <Clock3Icon />,
            countVariant: 'secondary',
        },
        {
            label: 'Stale',
            value: 'stale',
            count: statistics.stale,
            active: filters.status === 'stale',
            icon: <ShieldAlertIcon />,
            countVariant: 'warning',
        },
    ];

    const columns: DatagridColumn<QueueMonitorListItem>[] = [
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-32',
            cellClassName: 'w-32',
            sortable: true,
            sortKey: 'status',
            cell: (monitor) => (
                <Badge
                    variant={
                        STATUS_VARIANTS[
                            monitor.status_label.toLowerCase() as keyof typeof STATUS_VARIANTS
                        ] ?? 'outline'
                    }
                >
                    {monitor.status_label}
                </Badge>
            ),
        },
        {
            key: 'name',
            header: 'Job',
            sortable: true,
            cell: (monitor) => (
                <div className="flex min-w-0 flex-col gap-1">
                    <span className="truncate font-medium text-foreground">
                        {monitor.name}
                    </span>
                    <span className="truncate text-xs text-muted-foreground">
                        Queue: {monitor.queue ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            key: 'queue',
            header: 'Queue',
            headerClassName: 'w-32',
            cellClassName: 'w-32',
            sortable: true,
            cell: (monitor) =>
                monitor.queue ? (
                    <button
                        type="button"
                        className="text-sm font-medium text-foreground transition-colors hover:text-primary"
                        onClick={() => {
                            router.get(
                                route('app.masters.queue-monitor.index'),
                                {
                                    ...filters,
                                    queue: monitor.queue,
                                    page: 1,
                                },
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    replace: true,
                                },
                            );
                        }}
                    >
                        {monitor.queue}
                    </button>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'attempt',
            header: 'Attempt',
            headerClassName: 'w-24 text-center',
            cellClassName: 'w-24 text-center',
            sortable: true,
        },
        {
            key: 'duration',
            header: 'Duration',
            headerClassName: 'w-28',
            cellClassName: 'w-28 text-muted-foreground',
            sortable: true,
        },
        {
            key: 'wait',
            header: 'Wait',
            headerClassName: 'w-28',
            cellClassName: 'w-28 text-muted-foreground',
            sortable: true,
        },
        {
            key: 'started_at',
            header: 'Started',
            headerClassName: 'w-32',
            cellClassName: 'w-32 text-muted-foreground',
            sortable: true,
            sortKey: 'started_at',
        },
        {
            key: 'exception_message',
            header: 'Error',
            cell: (monitor) =>
                monitor.exception_message ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            setSelectedException(monitor.exception_message)
                        }
                    >
                        <AlertTriangleIcon data-icon="inline-start" />
                        View error
                    </Button>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    const rowActions = (monitor: QueueMonitorListItem): DatagridAction[] =>
        Object.entries(monitor.actions).map(([key, action]) =>
            mapMonitorAction(key, action),
        );

    const bulkActions: DatagridBulkAction<QueueMonitorListItem>[] =
        ui.allowDeletion
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Delete selected',
                      icon: <Trash2Icon />,
                      variant: 'destructive',
                      confirm: 'Delete the selected monitor entries?',
                      onSelect: (rows, clearSelection) => {
                          router.post(
                              route('app.masters.queue-monitor.bulk-action'),
                              {
                                  action: 'delete',
                                  ids: rows.map((row) => row.id),
                              },
                              {
                                  preserveScroll: true,
                                  onSuccess: () => clearSelection(),
                              },
                          );
                      },
                  },
              ]
            : [];

    const chartSeries = useMemo(() => buildChartSeries(chartData), [chartData]);

    const refreshLabel = formatRelativeTime(lastUpdatedAt, clockNow);
    const workerHealthVariant =
        liveWorkerStats &&
        liveWorkerStats.running_workers < liveWorkerStats.configured_workers
            ? 'warning'
            : 'success';

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Queue Monitor"
            description="Track job throughput, failures, worker health, and queue pressure in one place."
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={paused ? 'warning' : 'secondary'}>
                        {paused
                            ? 'Auto-refresh paused'
                            : `Updated ${refreshLabel}`}
                    </Badge>

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            router.reload({
                                only: [
                                    'monitors',
                                    'statistics',
                                    'metrics',
                                    'queueStats',
                                    'chartData',
                                    'workerStats',
                                    'queueOptions',
                                ],
                                onSuccess: () => {
                                    setLastUpdatedAt(Date.now());
                                },
                            });
                        }}
                    >
                        <RefreshCwIcon data-icon="inline-start" />
                        Refresh
                    </Button>

                    {ui.refreshInterval ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPaused((current) => !current)}
                        >
                            {paused ? (
                                <PlayCircleIcon data-icon="inline-start" />
                            ) : (
                                <PauseCircleIcon data-icon="inline-start" />
                            )}
                            {paused ? 'Resume' : 'Pause'}
                        </Button>
                    ) : null}

                    {ui.allowPurge ? (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                if (
                                    !window.confirm(
                                        'Purge every queue monitor entry? This cannot be undone.',
                                    )
                                ) {
                                    return;
                                }

                                router.post(
                                    route(
                                        'app.masters.queue-monitor.bulk-action',
                                    ),
                                    { action: 'purge' },
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <Trash2Icon data-icon="inline-start" />
                            Purge all
                        </Button>
                    ) : null}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {(statistics.failed > 0 || statistics.stale > 0) && (
                    <Alert variant="default" className="border-amber-200/80">
                        <AlertTriangleIcon />
                        <AlertTitle>Attention needed</AlertTitle>
                        <AlertDescription>
                            {statistics.failed > 0
                                ? `${statistics.failed} failed job${statistics.failed === 1 ? '' : 's'}`
                                : 'No failed jobs'}
                            {statistics.stale > 0
                                ? ` and ${statistics.stale} stale job${statistics.stale === 1 ? '' : 's'}`
                                : ''}{' '}
                            are currently tracked.
                        </AlertDescription>
                    </Alert>
                )}

                {metrics && metrics.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {metrics.map((metric) => (
                            <MetricCard
                                key={metric.title}
                                metric={metric}
                                metricsTimeFrame={ui.metricsTimeFrame}
                            />
                        ))}
                    </div>
                ) : null}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)]">
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b">
                            <div className="flex items-center gap-2">
                                <div className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <WorkflowIcon className="size-4" />
                                </div>
                                <div>
                                    <CardTitle>Queue health</CardTitle>
                                    <CardDescription>
                                        Running and queued are live. Success and
                                        failure counts cover the last{' '}
                                        {ui.metricsTimeFrame} days.
                                    </CardDescription>
                                </div>
                            </div>
                            <CardAction>
                                {filters.queue ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            router.get(
                                                route(
                                                    'app.masters.queue-monitor.index',
                                                ),
                                                {
                                                    ...filters,
                                                    queue: '',
                                                    page: 1,
                                                },
                                                {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                    replace: true,
                                                },
                                            );
                                        }}
                                    >
                                        <RotateCcwIcon data-icon="inline-start" />
                                        Clear queue filter
                                    </Button>
                                ) : null}
                            </CardAction>
                        </CardHeader>
                        <CardContent className="px-0">
                            {queueStats.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="px-4">
                                                Queue
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Queued
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Running
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Succeeded
                                            </TableHead>
                                            <TableHead className="text-center">
                                                Failed
                                            </TableHead>
                                            <TableHead className="px-4 text-right">
                                                Avg duration
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {queueStats.map((queue) => (
                                            <TableRow key={queue.queue}>
                                                <TableCell className="px-4">
                                                    <button
                                                        type="button"
                                                        className="font-medium text-foreground transition-colors hover:text-primary"
                                                        onClick={() => {
                                                            router.get(
                                                                route(
                                                                    'app.masters.queue-monitor.index',
                                                                ),
                                                                {
                                                                    ...filters,
                                                                    queue: queue.queue,
                                                                    page: 1,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                    preserveState: true,
                                                                    replace: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        {queue.queue}
                                                    </button>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge variant="secondary">
                                                        {queue.queued}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge variant="info">
                                                        {queue.running}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge variant="success">
                                                        {queue.succeeded}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge
                                                        variant={
                                                            queue.failed > 0
                                                                ? 'danger'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {queue.failed}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="px-4 text-right font-medium">
                                                    {queue.avg_duration !== null
                                                        ? `${queue.avg_duration}s`
                                                        : '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <EmptyPanel
                                    icon={<WorkflowIcon className="size-5" />}
                                    title="No queue activity yet"
                                    description="Queue summaries will appear after monitored jobs start running."
                                />
                            )}
                        </CardContent>
                    </Card>

                    {liveWorkerStats ? (
                        <WorkerPanel
                            stats={liveWorkerStats}
                            loading={workersLoading}
                            healthVariant={workerHealthVariant}
                        />
                    ) : null}
                </div>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b">
                        <div className="flex items-center gap-2">
                            <div className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <BarChart3Icon className="size-4" />
                            </div>
                            <div>
                                <CardTitle>Throughput</CardTitle>
                                <CardDescription>
                                    Hourly succeeded and failed jobs across the
                                    last {ui.chartHours} hours.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {chartSeries.length > 0 && chartData ? (
                            <ThroughputChart
                                chartData={chartData}
                                chartSeries={chartSeries}
                            />
                        ) : (
                            <EmptyPanel
                                icon={<BarChart3Icon className="size-5" />}
                                title="No snapshot data yet"
                                description="Snapshots are collected hourly. Once the next aggregation runs, throughput trends will render here."
                            />
                        )}
                    </CardContent>
                </Card>

                <Datagrid
                    action={route('app.masters.queue-monitor.index')}
                    rows={monitors}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{
                        name: 'status',
                        items: statusTabs,
                    }}
                    getRowKey={(monitor) => monitor.id}
                    rowActions={rowActions}
                    bulkActions={bulkActions}
                    isRowSelectable={(monitor) =>
                        ui.allowDeletion && Boolean(monitor.actions.delete)
                    }
                    sorting={{
                        sort: filters.sort,
                        direction: filters.direction,
                    }}
                    perPage={{
                        value: filters.per_page,
                        options: [10, 25, 35, 50, 100],
                    }}
                    view={{
                        value: filters.view,
                        storageKey: 'queue-monitor-datagrid-view',
                    }}
                    renderCardHeader={(monitor) => (
                        <>
                            <Badge
                                variant={
                                    STATUS_VARIANTS[
                                        monitor.status_label.toLowerCase() as keyof typeof STATUS_VARIANTS
                                    ] ?? 'outline'
                                }
                            >
                                {monitor.status_label}
                            </Badge>
                            <div className="min-w-0 flex-1">
                                <div className="truncate font-medium text-foreground">
                                    {monitor.name}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    Queue: {monitor.queue ?? '—'}
                                </div>
                            </div>
                        </>
                    )}
                    renderCard={(monitor) => (
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <Card size="sm" className="bg-muted/20">
                                <CardContent className="space-y-1">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Attempt
                                    </div>
                                    <div className="text-base font-medium">
                                        {monitor.attempt}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card size="sm" className="bg-muted/20">
                                <CardContent className="space-y-1">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Duration
                                    </div>
                                    <div className="text-base font-medium">
                                        {monitor.duration}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card size="sm" className="bg-muted/20">
                                <CardContent className="space-y-1">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Wait
                                    </div>
                                    <div className="text-base font-medium">
                                        {monitor.wait}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card size="sm" className="bg-muted/20">
                                <CardContent className="space-y-1">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Started
                                    </div>
                                    <div className="text-base font-medium">
                                        {monitor.started_at}
                                    </div>
                                </CardContent>
                            </Card>
                            {monitor.exception_message ? (
                                <div className="sm:col-span-2 xl:col-span-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setSelectedException(
                                                monitor.exception_message,
                                            )
                                        }
                                    >
                                        <AlertTriangleIcon data-icon="inline-start" />
                                        View error details
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    )}
                    submitLabel="Apply filters"
                    submitButtonVariant="outline"
                    empty={{
                        icon: <SearchXIcon />,
                        title: 'No jobs match this view',
                        description:
                            'Try a different queue, clear your metadata filters, or wait for the next monitored job.',
                    }}
                />

                <ExceptionDialog
                    exception={selectedException}
                    onOpenChange={(open) => {
                        if (!open) {
                            setSelectedException(null);
                        }
                    }}
                />
            </div>
        </AppLayout>
    );
}

function mapMonitorAction(
    key: string,
    action: QueueMonitorRowAction,
): DatagridAction {
    if (key === 'retry') {
        return {
            label: action.label,
            href: action.url,
            method: action.method,
            icon: <RotateCcwIcon />,
            confirm: action.confirm ?? 'Retry this failed job?',
        };
    }

    if (key === 'delete') {
        return {
            label: action.label,
            href: action.url,
            method: action.method,
            icon: <Trash2Icon />,
            confirm: action.confirm ?? 'Delete this monitor entry?',
            variant: 'destructive',
        };
    }

    return {
        label: action.label,
        href: action.url,
        method: action.method,
    };
}

function buildChartSeries(chartData: QueueMonitorChartData | null): Array<{
    key: string;
    queue: string;
    kind: 'succeeded' | 'failed';
    label: string;
    color: string;
    strokeDasharray?: string;
}> {
    if (!chartData || chartData.queues.length === 0) {
        return [];
    }

    return chartData.queues.flatMap((queue, index) => {
        const color = CHART_PALETTE[index % CHART_PALETTE.length];
        const series: Array<{
            key: string;
            queue: string;
            kind: 'succeeded' | 'failed';
            label: string;
            color: string;
            strokeDasharray?: string;
        }> = [
            {
                key: `queue_${index}_succeeded`,
                queue,
                kind: 'succeeded' as const,
                label: `${queue} succeeded`,
                color,
            },
        ];

        const hasFailures = (chartData.datasets[queue]?.failed ?? []).some(
            (value) => value > 0,
        );

        if (hasFailures) {
            series.push({
                key: `queue_${index}_failed`,
                queue,
                kind: 'failed' as const,
                label: `${queue} failed`,
                color,
                strokeDasharray: '6 4',
            });
        }

        return series;
    });
}

function formatMetricValue(metric: QueueMonitorMetric): string {
    const hasSecondsSuffix = metric.format.endsWith('s');

    if (metric.format.includes('%0.2f')) {
        return `${metric.value.toFixed(2)}${hasSecondsSuffix ? 's' : ''}`;
    }

    return `${Math.round(metric.value)}${hasSecondsSuffix ? 's' : ''}`;
}

function formatRelativeTime(timestamp: number, now: number): string {
    const deltaSeconds = Math.max(0, Math.round((now - timestamp) / 1000));

    if (deltaSeconds < 5) {
        return 'just now';
    }

    if (deltaSeconds < 60) {
        return `${deltaSeconds}s ago`;
    }

    const deltaMinutes = Math.round(deltaSeconds / 60);
    if (deltaMinutes < 60) {
        return `${deltaMinutes}m ago`;
    }

    const deltaHours = Math.round(deltaMinutes / 60);

    return `${deltaHours}h ago`;
}

function MetricCard({
    metric,
    metricsTimeFrame,
}: {
    metric: QueueMonitorMetric;
    metricsTimeFrame: number;
}) {
    const currentValue = formatMetricValue(metric);
    const previousValue =
        metric.previousValue !== null
            ? formatMetricValue({
                  ...metric,
                  value: metric.previousValue,
              })
            : null;
    const hasChanged =
        metric.previousValue !== null && metric.value !== metric.previousValue;
    const hasIncreased =
        metric.previousValue !== null && metric.value > metric.previousValue;

    return (
        <Card className="border-none bg-linear-to-br from-card via-card to-primary/5 shadow-sm">
            <CardHeader>
                <CardDescription>
                    {metric.title}
                    <span className="ml-1 text-xs text-muted-foreground/80">
                        · Last {metricsTimeFrame} days
                    </span>
                </CardDescription>
                <CardTitle className="text-3xl">{currentValue}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground">
                {previousValue ? (
                    <span
                        className={
                            hasChanged
                                ? hasIncreased
                                    ? 'text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]'
                                    : 'text-amber-700 dark:text-amber-300'
                                : undefined
                        }
                    >
                        {hasChanged
                            ? hasIncreased
                                ? 'Up from '
                                : 'Down from '
                            : 'No change from '}
                        {previousValue}
                    </span>
                ) : (
                    'No comparison window available yet.'
                )}
            </CardContent>
        </Card>
    );
}

function WorkerPanel({
    stats,
    loading,
    healthVariant,
}: {
    stats: QueueWorkerStats;
    loading: boolean;
    healthVariant: 'warning' | 'success';
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b">
                <div className="flex items-center gap-2">
                    <div className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <ServerCogIcon className="size-4" />
                    </div>
                    <div>
                        <CardTitle>Worker health</CardTitle>
                        <CardDescription>
                            Supervisor status and detected queue workers for
                            this application.
                        </CardDescription>
                    </div>
                </div>
                <CardAction>
                    <div className="flex items-center gap-2">
                        {loading ? (
                            <Badge variant="secondary">Refreshing</Badge>
                        ) : null}
                        <Badge
                            variant={
                                stats.supervisor_running ? 'success' : 'danger'
                            }
                        >
                            {stats.supervisor_running
                                ? 'Supervisor running'
                                : 'Supervisor stopped'}
                        </Badge>
                    </div>
                </CardAction>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-3">
                    <CompactStatCard
                        label="Configured"
                        value={stats.configured_workers}
                    />
                    <CompactStatCard
                        label="Running"
                        value={stats.running_workers}
                        variant={healthVariant}
                    />
                    <CompactStatCard
                        label="Status"
                        value={stats.supervisor_status}
                    />
                </div>

                {stats.processes.length > 0 ? (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>PID</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Memory</TableHead>
                                <TableHead className="text-right">
                                    Uptime
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {stats.processes.map((process) => (
                                <TableRow key={process.pid}>
                                    <TableCell className="font-mono text-xs">
                                        {process.pid}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                process.status === 'idle' ||
                                                process.status === 'running'
                                                    ? 'success'
                                                    : process.status ===
                                                        'waiting'
                                                      ? 'warning'
                                                      : 'danger'
                                            }
                                        >
                                            {process.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {process.memory_mb.toFixed(1)} MB
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {process.uptime}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                ) : (
                    <EmptyPanel
                        icon={<ServerCogIcon className="size-5" />}
                        title="No worker processes detected"
                        description="Supervisor may be up without active queue workers, or this app may not be configured yet."
                    />
                )}

                {(stats.program_name || stats.command || stats.log_file) && (
                    <div className="rounded-xl border bg-muted/30 p-3">
                        <div className="grid gap-2 text-sm">
                            {stats.program_name ? (
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Program
                                    </span>
                                    <code className="rounded bg-background px-2 py-1 text-xs">
                                        {stats.program_name}
                                    </code>
                                </div>
                            ) : null}
                            {stats.log_file ? (
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Log file
                                    </span>
                                    <code className="truncate rounded bg-background px-2 py-1 text-xs">
                                        {stats.log_file}
                                    </code>
                                </div>
                            ) : null}
                            {stats.command ? (
                                <div className="space-y-1">
                                    <div className="text-muted-foreground">
                                        Command
                                    </div>
                                    <code className="block rounded bg-background px-2 py-1 text-xs break-all">
                                        {stats.command}
                                    </code>
                                </div>
                            ) : null}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ThroughputChart({
    chartData,
    chartSeries,
}: {
    chartData: QueueMonitorChartData;
    chartSeries: Array<{
        key: string;
        queue: string;
        kind: 'succeeded' | 'failed';
        label: string;
        color: string;
        strokeDasharray?: string;
    }>;
}) {
    const rows = chartData.labels.map((label, index) => {
        const row: Record<string, string | number> = { label };

        for (const series of chartSeries) {
            row[series.key] =
                chartData.datasets[series.queue]?.[series.kind][index] ?? 0;
        }

        return row;
    });

    const config = Object.fromEntries(
        chartSeries.map((series) => [
            series.key,
            {
                label: series.label,
                color: series.color,
            },
        ]),
    );

    return (
        <ChartContainer config={config} className="h-[320px] w-full">
            <LineChart data={rows} margin={{ left: 8, right: 8, top: 8 }}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    minTickGap={24}
                />
                <YAxis
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                />
                <ChartTooltip
                    content={<ChartTooltipContent indicator="line" />}
                />
                <ChartLegend content={<ChartLegendContent />} />
                {chartSeries.map((series) => (
                    <Line
                        key={series.key}
                        type="monotone"
                        dataKey={series.key}
                        stroke={`var(--color-${series.key})`}
                        strokeWidth={2}
                        dot={false}
                        strokeDasharray={series.strokeDasharray}
                    />
                ))}
            </LineChart>
        </ChartContainer>
    );
}

function EmptyPanel({
    icon,
    title,
    description,
}: {
    icon: ReactNode;
    title: string;
    description: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center">
            <div className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                {icon}
            </div>
            <div className="space-y-1">
                <div className="font-medium text-foreground">{title}</div>
                <div className="max-w-md text-sm text-muted-foreground">
                    {description}
                </div>
            </div>
        </div>
    );
}

function CompactStatCard({
    label,
    value,
    variant = 'outline',
}: {
    label: string;
    value: string | number;
    variant?: 'outline' | 'success' | 'warning';
}) {
    return (
        <div className="rounded-xl border bg-muted/20 p-3">
            <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-2 flex items-center gap-2">
                <span
                    className={`size-2 rounded-full ${
                        variant === 'success'
                            ? 'bg-[var(--success-foreground)] dark:bg-[var(--success-dark-foreground)]'
                            : variant === 'warning'
                              ? 'bg-amber-500'
                              : 'bg-muted-foreground/40'
                    }`}
                />
                <div className="text-lg font-semibold text-foreground">
                    {value}
                </div>
            </div>
        </div>
    );
}

function ExceptionDialog({
    exception,
    onOpenChange,
}: {
    exception: string | null;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={exception !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl p-0">
                <DialogHeader className="px-4 pt-4">
                    <DialogTitle>Error details</DialogTitle>
                    <DialogDescription>
                        Full exception output captured for this queue monitor
                        entry.
                    </DialogDescription>
                </DialogHeader>
                <ScrollArea className="max-h-[60vh] px-4">
                    <pre className="rounded-xl border bg-muted/30 p-4 text-xs leading-6 break-words whitespace-pre-wrap">
                        {exception}
                    </pre>
                </ScrollArea>
                <DialogFooter showCloseButton>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={async () => {
                            if (!exception) {
                                return;
                            }

                            await navigator.clipboard.writeText(exception);
                            showAppToast({
                                description: 'Error copied to clipboard.',
                            });
                        }}
                    >
                        <CopyIcon data-icon="inline-start" />
                        Copy
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
