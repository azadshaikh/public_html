import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldInertiaConfig } from '@/types/scaffold';

export type ActivityLogListItem = {
    id: number;
    checkbox: boolean;
    show_url: string;
    event: string;
    event_label: string;
    event_class: string;
    description: string;
    causer_name: string;
    subject_display: string;
    ip_address: string;
    user_agent: string;
    created_at: string;
    time_ago: string;
    severity: string;
    has_changes?: boolean;
    changes_count?: number;
    context?: Record<string, unknown>;
    request_details?: {
        url: string;
        method: string;
    };
    actions: Record<
        string,
        {
            url: string;
            label: string;
            icon: string;
            class: string;
            method: string;
            confirm?: string;
        }
    >;
};

export type ActivityLogStatistics = {
    total: number;
    today: number;
    this_week: number;
    this_month: number;
    trash: number;
};

export type ActivityLogFilters = {
    search: string;
    status: 'all' | 'trash';
    event?: string;
    causer_id?: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type ActivityLogFilterOptions = {
    event: { value: string; label: string }[];
    causer_id: { value: string; label: string }[];
};

export type ActivityLogsIndexPageProps = {
    config: ScaffoldInertiaConfig;
    logs: PaginatedData<ActivityLogListItem>;
    statistics: ActivityLogStatistics;
    filters: ActivityLogFilters;
    filterOptions: ActivityLogFilterOptions;
    status?: string;
    error?: string;
};

// ================================================================
// Show page types
// ================================================================

export type ActivityLogShowDetail = {
    id: number;
    log_name: string | null;
    description: string;
    event: string;
    event_formatted?: string;
    causer_name: string;
    subject_display: string;
    subject_type: string | null;
    ip_address: string | null;
    browser: string | null;
    request_url: string | null;
    severity: string;
    created_at: string;
    created_at_formatted: string;
    time_ago: string;
    properties: Record<string, unknown>;
};

export type ActivityLogChange = {
    from: string | null;
    to: string | null;
};

export type ActivityLogsShowPageProps = {
    activityLog: ActivityLogShowDetail;
    changes_summary?: Record<string, ActivityLogChange>;
    status?: string;
    error?: string;
};
