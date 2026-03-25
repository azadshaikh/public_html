import type { Key, ReactNode } from 'react';
import type { PaginatedData } from '@/types';
import type { ScaffoldColumnConfig } from '@/types/scaffold';
import type { BadgeVariant } from '@/types/ui';

export type DatagridFilterOption = {
    value: string;
    label: string;
};

export type DatagridSearchFilter = {
    type: 'search';
    name: string;
    value: string;
    placeholder: string;
    className?: string;
};

export type DatagridSelectFilter = {
    type: 'select';
    name: string;
    value: string;
    label?: string;
    placeholder?: string;
    options: DatagridFilterOption[];
    multiple?: boolean;
    className?: string;
};

export type DatagridDateRangeFilter = {
    type: 'date_range';
    name: string;
    value: string;
    label?: string;
    className?: string;
};

export type DatagridBooleanFilter = {
    type: 'boolean';
    name: string;
    value: string;
    label?: string;
    trueLabel?: string;
    falseLabel?: string;
    className?: string;
};

export type DatagridNumberFilter = {
    type: 'number';
    name: string;
    value: string;
    label?: string;
    min?: number;
    max?: number;
    step?: number;
    placeholder?: string;
    className?: string;
};

export type DatagridHiddenFilter = {
    type: 'hidden';
    name: string;
    value: string;
};

export type DatagridFilter =
    | DatagridSearchFilter
    | DatagridSelectFilter
    | DatagridDateRangeFilter
    | DatagridBooleanFilter
    | DatagridNumberFilter
    | DatagridHiddenFilter;

export type DatagridTab = {
    label: string;
    value: string;
    count?: number;
    active: boolean;
    icon?: ReactNode;
    /** Badge variant for the count pill. Supports all Badge variants. */
    countVariant?:
        | 'default'
        | 'secondary'
        | 'success'
        | 'warning'
        | 'info'
        | 'danger'
        | 'destructive'
        | 'outline';
};

export type DatagridColumnType =
    | 'text'
    | 'badge'
    | 'boolean'
    | 'currency'
    | 'image'
    | 'link'
    | 'date';

export type DatagridColumn<T> = {
    key: string;
    header: string;
    width?: string | number;
    headerClassName?: string;
    cellClassName?: string;
    cell?: (row: T) => ReactNode;
    type?: DatagridColumnType;
    sortable?: boolean;
    sortKey?: string;
    cardLabel?: string;
    /**
     * Static map of cell value → Badge variant name.
     * e.g. { active: 'success', banned: 'danger', pending: 'warning' }
     * Only used when type is 'badge'.
     */
    badgeVariants?: Record<string, BadgeVariant>;
    /**
     * Field name in the row data that holds the Badge variant.
     * e.g. 'status_badge' reads row['status_badge'] for the variant.
     * Only used when type is 'badge'. Takes precedence over badgeVariants map.
     */
    badgeVariantKey?: string;
};

export type DatagridAction = {
    label: string;
    icon?: ReactNode;
    href?: string;
    onSelect?: () => void;
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    confirm?: string;
    variant?: 'default' | 'destructive';
    disabled?: boolean;
    hidden?: boolean;
};

export type DatagridBulkAction<T> = {
    key: string;
    label: string;
    icon?: ReactNode;
    variant?: 'default' | 'destructive';
    confirm?: string;
    onSelect: (rows: T[], clearSelection: () => void) => void;
    disabled?: boolean | ((rows: T[]) => boolean);
};

export type DatagridViewMode = 'table' | 'cards';

export type DatagridProps<T> = {
    action: string;
    rows: PaginatedData<T>;
    columns: DatagridColumn<T>[];
    scaffoldColumns?: ScaffoldColumnConfig[];
    filters?: DatagridFilter[];
    tabs?: {
        name: string;
        items: DatagridTab[];
    };
    getRowKey: (row: T) => Key;
    rowActions?: (row: T) => DatagridAction[];
    bulkActions?: DatagridBulkAction<T>[];
    isRowSelectable?: (row: T) => boolean;
    empty: {
        icon: ReactNode;
        title: string;
        description: string;
        action?: {
            label: string;
            href: string;
        };
    };
    sorting?: {
        sort: string;
        direction: 'asc' | 'desc';
        sortParamName?: string;
        directionParamName?: string;
    };
    perPage?: {
        value: number;
        options: number[];
        paramName?: string;
    };
    view?: {
        value: DatagridViewMode;
        paramName?: string;
        storageKey?: string;
    };
    renderCardHeader?: (row: T) => ReactNode;
    renderCard?: (row: T) => ReactNode;
    cardGridClassName?: string;
    summary?: string;
    showHeading?: boolean;
    submitLabel?: string;
    submitButtonVariant?: 'default' | 'outline' | 'secondary' | 'ghost';
    submitButtonSize?: 'comfortable' | 'default' | 'sm' | 'xs';
    title?: string;
    description?: string;
    className?: string;
    searchDebounceMs?: number;
};
