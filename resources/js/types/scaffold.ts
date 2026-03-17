import type { PaginatedData } from '@/types/pagination';

export type ScaffoldFilterOption = {
    value: string | number;
    label: string;
    disabled?: boolean;
    description?: string | null;
};

export type ScaffoldStatusTabConfig = {
    key: string;
    label: string;
    value?: string;
    icon?: string;
    color?: string;
    default?: boolean;
};

export type ScaffoldFilterConfig = {
    type: string;
    key: string;
    label?: string;
    options?: Record<string, unknown> | ScaffoldFilterOption[];
    multiple?: boolean;
    placeholder?: string;
    min?: number;
    max?: number;
    step?: number;
};

export type ScaffoldActionConfig = {
    key: string;
    label: string;
    icon?: string | null;
    method?: string;
    confirm?: string;
    confirmBulk?: string;
    scope?: 'row' | 'bulk' | 'both';
    variant?: string;
};

export type ScaffoldRowActionPayload = {
    key: string;
    label: string;
    icon?: string | null;
    url?: string;
    method?: string;
    confirm?: string;
    variant?: string;
    disabled?: boolean;
    hidden?: boolean;
};

export type ScaffoldSettings = {
    perPage: number;
    defaultSort: string | null;
    defaultDirection: 'asc' | 'desc';
    enableBulkActions: boolean;
    enableExport: boolean;
    hasNotes: boolean;
    entityName: string;
    entityPlural: string;
    routePrefix: string;
    statusField: string | null;
};

export type ScaffoldInertiaConfig = {
    columns: Record<string, unknown>[];
    filters: ScaffoldFilterConfig[];
    actions: ScaffoldActionConfig[];
    statusTabs: ScaffoldStatusTabConfig[];
    settings: ScaffoldSettings;
};

export type ScaffoldFilterState = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view?: string;
    [key: string]: string | number | boolean | undefined | null;
};

export type ScaffoldIndexPageProps<T> = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<T>;
    filters: ScaffoldFilterState;
    statistics: Record<string, number>;
    status?: string;
    error?: string;
};
