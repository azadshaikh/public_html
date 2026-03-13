import type { PaginatedData } from '@/types/pagination';

export type NotificationListItem = {
    id: string;
    type: string;
    category: string;
    priority: string;
    title: string | null;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
    updated_at: string;
    is_read: boolean;
    title_text: string;
    message: string;
    sanitized_message: string;
    icon: string;
    url: string | null;
    category_label: string;
    category_color: string;
    category_badge: string;
    priority_label: string;
    priority_badge: string;
};

export type NotificationStats = {
    total: number;
    unread: number;
    read: number;
    high_priority: number;
};

export type NotificationFilters = {
    search: string;
    filter: string;
    category: string;
    priority: string;
};

export type SelectOption = {
    value: string;
    label: string;
};

export type NotificationsIndexPageProps = {
    notifications: PaginatedData<NotificationListItem>;
    stats: NotificationStats;
    filters: NotificationFilters;
    categoryOptions: SelectOption[];
    priorityOptions: SelectOption[];
    statusOptions: SelectOption[];
    status?: string;
    error?: string;
};
