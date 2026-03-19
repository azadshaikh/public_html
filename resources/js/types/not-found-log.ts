import type { RecentStats } from '@/types/login-attempt';
import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldInertiaConfig } from '@/types/scaffold';

export type NotFoundLogListItem = {
    id: number;
    checkbox: boolean;
    show_url: string;
    url: string;
    url_display: string;
    full_url: string | null;
    referer: string | null;
    referer_display: string;
    ip_address: string;
    ip_address_raw: string;
    user_agent: string | null;
    method: string;
    is_bot: boolean;
    is_suspicious: boolean;
    user_id: number | null;
    user_name: string;
    browser: string;
    metadata: Record<string, unknown> | null;
    status_badge: string;
    status_badge_label: string;
    status_badge_class: string;
    created_at: string;
    time_ago: string | null;
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

export type NotFoundLogStatistics = {
    total: number;
    suspicious: number;
    bots: number;
    human: number;
    today: number;
    trash: number;
};

export type NotFoundLogFilters = {
    search: string;
    created_at: string;
    status: 'all' | 'suspicious' | 'bots' | 'human' | 'trash';
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type NotFoundLogsIndexPageProps = {
    config: ScaffoldInertiaConfig;
    notFoundLogs: PaginatedData<NotFoundLogListItem>;
    statistics: NotFoundLogStatistics;
    filters: NotFoundLogFilters;
    status?: string;
    error?: string;
};

// ================================================================
// Show page types
// ================================================================

export type NotFoundLogShowDetail = {
    id: number;
    url: string;
    full_url: string | null;
    referer: string | null;
    ip_address: string;
    user_agent: string | null;
    method: string;
    is_bot: boolean;
    is_suspicious: boolean;
    user_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    deleted_at: string | null;
    user?: {
        id: number;
        name: string;
    };
};

export type NotFoundLogsShowPageProps = {
    notFoundLog: NotFoundLogShowDetail;
    recentUrlStats: RecentStats;
    recentIpStats: RecentStats;
    status?: string;
    error?: string;
};
