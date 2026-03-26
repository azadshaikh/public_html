import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldInertiaConfig } from '@/types/scaffold';

export type QueueMonitorRowAction = {
    url: string;
    label: string;
    icon: string;
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    confirm?: string;
    variant?: 'default' | 'primary' | 'destructive';
};

export type QueueMonitorListItem = {
    id: number;
    status: number;
    status_label: string;
    status_class: string;
    name: string;
    queue: string | null;
    attempt: number;
    duration: string;
    wait: string;
    started_at: string;
    exception_message: string | null;
    actions: Record<string, QueueMonitorRowAction>;
};

export type QueueMonitorStatistics = {
    total: number;
    succeeded: number;
    failed: number;
    running: number;
    queued: number;
    stale: number;
};

export type QueueMonitorFilters = {
    search: string;
    queue: string;
    metadata_key: string;
    metadata_value: string;
    status: 'all' | 'succeeded' | 'failed' | 'running' | 'queued' | 'stale';
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type QueueMonitorMetric = {
    title: string;
    value: number;
    previousValue: number | null;
    format: string;
};

export type QueueStat = {
    queue: string;
    running: number;
    queued: number;
    succeeded: number;
    failed: number;
    avg_duration: number | null;
};

export type QueueMonitorChartData = {
    labels: string[];
    queues: string[];
    datasets: Record<
        string,
        {
            succeeded: number[];
            failed: number[];
        }
    >;
};

export type QueueWorkerProcess = {
    pid: number;
    memory_mb: number;
    uptime: string;
    status: string;
};

export type QueueWorkerStats = {
    supervisor_running: boolean;
    supervisor_status: string;
    program_name: string | null;
    configured_workers: number;
    running_workers: number;
    processes: QueueWorkerProcess[];
    command: string | null;
    log_file: string | null;
};

export type QueueMonitorUiConfig = {
    refreshInterval: number | null;
    metricsTimeFrame: number;
    chartHours: number;
    workerRefreshInterval: number | null;
    allowRetry: boolean;
    allowDeletion: boolean;
    allowPurge: boolean;
    allowMarkStale: boolean;
    allowClearQueue: boolean;
};

export type QueueMonitorIndexPageProps = {
    config: ScaffoldInertiaConfig;
    monitors: PaginatedData<QueueMonitorListItem>;
    statistics: QueueMonitorStatistics;
    filters: QueueMonitorFilters;
    metrics: QueueMonitorMetric[] | null;
    queueStats: QueueStat[];
    chartData: QueueMonitorChartData | null;
    workerStats: QueueWorkerStats | null;
    queueOptions: Array<{ value: string; label: string }>;
    ui: QueueMonitorUiConfig;
    status?: string;
    error?: string;
};
