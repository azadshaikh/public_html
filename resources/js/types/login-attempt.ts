import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldInertiaConfig } from '@/types/scaffold';

export type LoginAttemptListItem = {
    id: number;
    checkbox: boolean;
    show_url: string;
    email: string;
    ip_address: string;
    ip_address_raw: string;
    status: string;
    status_label: string;
    status_class: string;
    status_badge: string;
    failure_reason: string | null;
    failure_reason_label: string;
    user_id: number | null;
    user_name: string;
    user_agent: string | null;
    browser: string;
    metadata: Record<string, unknown> | null;
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

export type LoginAttemptStatistics = {
    total: number;
    success: number;
    failed: number;
    blocked: number;
    trash: number;
};

export type LoginAttemptFilters = {
    search: string;
    created_at: string;
    status: 'all' | 'success' | 'failed' | 'blocked' | 'trash';
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type LoginAttemptsIndexPageProps = {
    config: ScaffoldInertiaConfig;
    loginAttempts: PaginatedData<LoginAttemptListItem>;
    statistics: LoginAttemptStatistics;
    filters: LoginAttemptFilters;
    status?: string;
    error?: string;
};

// ================================================================
// Show page types
// ================================================================

export type LoginAttemptShowDetail = {
    id: number;
    email: string;
    ip_address: string;
    user_agent: string | null;
    status: string;
    failure_reason: string | null;
    user_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    deleted_at: string | null;
    user?: {
        id: number;
        name: string;
    };
};

export type RecentStats = {
    total: number;
    success?: number;
    failed?: number;
    blocked?: number;
    unique_emails?: number;
    unique_ips?: number;
    suspicious?: number;
    unique_urls?: number;
};

export type LoginAttemptsShowPageProps = {
    loginAttempt: LoginAttemptShowDetail;
    recentEmailStats: RecentStats;
    recentIpStats: RecentStats;
    status?: string;
    error?: string;
};
