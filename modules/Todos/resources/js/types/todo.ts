import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldInertiaConfig } from '@/types/scaffold';

// ================================================================
// Option type
// ================================================================

export type TodoOption = {
    value: string;
    label: string;
};

// ================================================================
// List item (used in index datagrid)
// ================================================================

export type TodoListItem = {
    id: number;
    title: string;
    description_preview: string | null;
    status: string;
    status_label: string;
    status_badge: string;
    status_class: string;
    priority: string;
    priority_label: string;
    priority_badge: string;
    priority_class: string;
    visibility: string;
    is_starred: boolean;
    is_overdue: boolean;
    is_trashed: boolean;
    due_date_formatted: string | null;
    start_date_formatted: string | null;
    completed_at_formatted: string | null;
    assigned_to_name: string;
    owner_name: string | null;
    labels_list: string[];
    show_url: string;
    edit_url: string;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

// ================================================================
// Statistics
// ================================================================

export type TodoStatistics = {
    total: number;
    pending: number;
    in_progress: number;
    completed: number;
    on_hold: number;
    cancelled: number;
    trash: number;
};

// ================================================================
// Filters
// ================================================================

export type TodoFilters = {
    search: string;
    status: string;
    priority: string;
    visibility: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view?: 'table' | 'cards';
};

// ================================================================
// Form values
// ================================================================

export type TodoFormValues = {
    title: string;
    description: string;
    status: string;
    priority: string;
    visibility: string;
    start_date: string;
    due_date: string;
    is_starred: boolean;
    assigned_to: string;
    labels: string;
};

// ================================================================
// Show detail (includes raw model + resource fields)
// ================================================================

export type TodoShowDetail = {
    id: number;
    user_id: number;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
    status_class: string;
    priority: string;
    priority_label: string;
    priority_class: string;
    visibility: string;
    is_starred: boolean;
    is_overdue: boolean;
    assigned_to: number | null;
    assigned_to_name: string;
    owner_name: string | null;
    labels: string | null;
    labels_list: string[];
    start_date_formatted: string | null;
    due_date_formatted: string | null;
    completed_at_formatted: string | null;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
    deleted_at: string | null;
    show_url: string;
    edit_url: string;
};

// ================================================================
// Page props
// ================================================================

export type TodoIndexPageProps = {
    config: ScaffoldInertiaConfig;
    todos: PaginatedData<TodoListItem>;
    statistics: TodoStatistics;
    filters: TodoFilters;
    status?: string;
    error?: string;
};

export type TodoFormOptions = {
    initialValues: TodoFormValues;
    statusOptions: TodoOption[];
    priorityOptions: TodoOption[];
    visibilityOptions: TodoOption[];
    assigneeOptions: TodoOption[];
};

export type TodoCreatePageProps = TodoFormOptions;

export type TodoEditPageProps = TodoFormOptions & {
    todo: TodoShowDetail;
};

export type TodoShowPageProps = {
    todo: TodoShowDetail;
    status?: string;
    error?: string;
};
