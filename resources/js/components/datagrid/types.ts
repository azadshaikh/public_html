import type { Key, ReactNode } from 'react';
import type { PaginatedData } from '@/types';

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
    options: DatagridFilterOption[];
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
    | DatagridHiddenFilter;

export type DatagridTab = {
    label: string;
    value: string;
    count?: number;
    active: boolean;
    icon?: ReactNode;
    countVariant?: 'default' | 'secondary' | 'outline' | 'destructive';
};

export type DatagridColumn<T> = {
    key: string;
    header: string;
    headerClassName?: string;
    cellClassName?: string;
    cell: (row: T) => ReactNode;
    sortable?: boolean;
    sortKey?: string;
    cardLabel?: string;
};

export type DatagridAction = {
    label: string;
    icon?: ReactNode;
    href?: string;
    onSelect?: () => void;
    variant?: 'default' | 'destructive';
    disabled?: boolean;
    hidden?: boolean;
};

export type DatagridBulkAction<T> = {
    key: string;
    label: string;
    icon?: ReactNode;
    variant?: 'default' | 'destructive';
    onSelect: (rows: T[], clearSelection: () => void) => void;
    disabled?: boolean | ((rows: T[]) => boolean);
};

export type DatagridViewMode = 'table' | 'cards';

export type DatagridProps<T> = {
    action: string;
    rows: PaginatedData<T>;
    columns: DatagridColumn<T>[];
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
    summary?: string;
    submitLabel?: string;
    submitButtonVariant?: 'default' | 'outline' | 'secondary' | 'ghost';
    submitButtonSize?: 'comfortable' | 'default' | 'sm' | 'xs';
    title?: string;
    description?: string;
    className?: string;
    searchDebounceMs?: number;
};
