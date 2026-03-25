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
    time_ago?: string;
    url_backend?: string | null;
    url_frontend?: string | null;
    content_links: NotificationContentLink[];
};

export type NotificationContentLink = {
    label: string;
    href: string;
    external: boolean;
};

export type NotificationDropdownItem = Pick<
    NotificationListItem,
    | 'id'
    | 'title_text'
    | 'sanitized_message'
    | 'icon'
    | 'category_label'
    | 'category_color'
    | 'priority'
    | 'priority_label'
    | 'time_ago'
    | 'is_read'
    | 'created_at'
    | 'content_links'
>;

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

export type NotificationPreferenceValues = {
    categories: Record<string, boolean>;
    priorities: Record<string, boolean>;
};

export type NotificationPreferenceOption = {
    value: string;
    label: string;
};

export type NotificationsPreferencesPageProps = {
    notificationsEnabled: boolean;
    preferences: NotificationPreferenceValues;
    categoryPreferences: NotificationPreferenceOption[];
    priorityPreferences: NotificationPreferenceOption[];
};

export type NotificationShowPageProps = {
    notification: NotificationListItem;
};
