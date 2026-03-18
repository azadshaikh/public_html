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
    conditions?: Record<string, [string, string | string[]]>;
};

export type ScaffoldFormFieldConfig = {
    key: string;
    label?: string;
    type?: string;
    required?: boolean;
    description?: string | null;
    options?: ScaffoldFilterOption[] | Record<string, unknown>;
    placeholder?: string;
    multiple?: boolean;
    defaultValue?: unknown;
    [key: string]: unknown;
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

export type ScaffoldColumnConfig = {
    key: string;
    label: string;
    width?: string | number;
    visible?: boolean;
    [key: string]: unknown;
};

export type ScaffoldEmptyStateConfig = {
    icon?: string;
    title: string;
    message: string;
    action?: {
        label: string;
        url: string;
    };
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
    statusField: string | null;
};

export type ScaffoldInertiaConfig = {
    columns: ScaffoldColumnConfig[];
    filters: ScaffoldFilterConfig[];
    actions?: ScaffoldActionConfig[];
    statusTabs: ScaffoldStatusTabConfig[];
    form: ScaffoldFormFieldConfig[];
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
    empty_state_config?: ScaffoldEmptyStateConfig | null;
    status?: string;
    error?: string;
};
