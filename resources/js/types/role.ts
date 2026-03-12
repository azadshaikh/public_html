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
    description: string | null;
    is_system: boolean;
    permissions_count: number;
    users_count: number;
};

export type RoleFormValues = {
    name: string;
    display_name: string;
    description: string;
    permissions: number[];
};

export type RoleEditingTarget = RoleFormValues & {
    id: number;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
};

export type RolesIndexPageProps = {
    roles: PaginatedData<RoleListItem>;
    filters: {
        search: string;
        scope: 'all' | 'system' | 'custom';
        sort: 'role' | 'permissions' | 'users' | 'status';
        direction: 'asc' | 'desc';
        per_page: number;
        view: 'table' | 'cards';
    };
    stats: {
        total: number;
        system: number;
        custom: number;
    };
    status?: string;
    error?: string;
};

export type RoleFormPageProps = {
    initialValues: RoleFormValues;
    permissionGroups: PermissionGroup[];
};

export type RoleEditPageProps = RoleFormPageProps & {
    role: RoleEditingTarget;
};
