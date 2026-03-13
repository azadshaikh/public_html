import type { PaginatedData } from '@/types/pagination';

export type PermissionOption = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    module_slug: string | null;
};

export type PermissionGroup = {
    group: string;
    label: string;
    permissions: PermissionOption[];
};

export type RoleListItem = {
    id: number;
    name: string;
    display_name: string;
    guard_name: string;
    status: string;
    status_label: string;
    status_badge: string;
    is_system: boolean;
    is_trashed: boolean;
    show_url: string;
    permissions_count: number;
    users_count: number;
    created_at: string;
    updated_at: string | null;
};

export type RoleStatistics = {
    total: number;
    active: number;
    inactive: number;
    trash: number;
};

export type RoleFilters = {
    search: string;
    status: 'all' | 'active' | 'inactive' | 'trash';
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type RolesIndexPageProps = {
    roles: PaginatedData<RoleListItem>;
    statistics: RoleStatistics;
    filters: RoleFilters;
    status?: string;
    error?: string;
};

// ================================================================
// Show page types
// ================================================================

export type RoleShowDetail = {
    id: number;
    name: string;
    display_name: string;
    guard_name: string;
    status: string;
    status_label: string;
    status_badge: string;
    is_system: boolean;
    is_trashed: boolean;
    trashed_at: string | null;
    trashed_at_formatted: string | null;
    users_count: number;
    permissions_count: number;
    notes_count: number;
    created_at: string | null;
    created_at_formatted: string;
    updated_at: string | null;
    updated_at_formatted: string | null;
    created_by: string;
    updated_by: string;
};

export type RolesShowPageProps = {
    role: RoleShowDetail;
    permissionGroups: PermissionGroup[];
    status?: string;
    error?: string;
};

// ================================================================
// Form types
// ================================================================

export type RoleFormValues = {
    name: string;
    display_name: string;
    permissions: number[];
};

export type RoleEditingTarget = RoleFormValues & {
    id: number;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
};

export type RoleFormPageProps = {
    initialValues: RoleFormValues;
    permissionGroups: PermissionGroup[];
};

export type RoleEditPageProps = RoleFormPageProps & {
    role: RoleEditingTarget;
};
