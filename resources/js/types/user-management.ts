import type {
    AppNote,
    NoteBadgeVariant,
    NoteTarget,
    NoteVisibilityOption,
} from '@/types/notes';
import type { PaginatedData } from '@/types/pagination';

export type ManagedUserRole = {
    id: number;
    name: string;
    display_name: string;
};

/**
 * Row-level action from UserResource::getActions().
 * Each action has a url, method, and optional confirm dialog text.
 */
export type UserRowAction = {
    url: string;
    label: string;
    icon: string;
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    confirm?: string;
};

/**
 * User list item shape returned by UserResource for the DataGrid.
 */
export type UserListItem = {
    id: number;
    name: string;
    full_name: string;
    first_name: string;
    last_name: string;
    email: string;
    username: string;
    avatar: string | null;
    avatar_url: string | null;
    show_url: string;
    status: string;
    status_label: string;
    status_badge: NoteBadgeVariant;
    email_verified: boolean;
    email_verified_at: string | null;
    gender: string | null;
    tagline: string | null;
    bio: string | null;
    roles: string[];
    created_at: string;
    created_at_formatted: string;
    created_at_human: string;
    updated_at: string | null;
    updated_at_formatted: string | null;
    updated_at_human: string | null;
    last_access: string | null;
    deleted_at: string | null;
    actions: Record<string, UserRowAction>;
};

export type UserStatistics = {
    total: number;
    active: number;
    pending: number;
    suspended: number;
    banned: number;
    trash: number;
};

export type UserFilters = {
    search: string;
    role_id: string;
    email_verified: string;
    gender: string;
    created_at: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type RegistrationSettings = {
    enabled: boolean;
    auto_approve: boolean;
    require_verification: boolean;
    default_role_id: number;
    default_role_name: string | null;
    settings_url: string;
    pending_count: number;
    pending_route: string;
};

export type UsersIndexPageProps = {
    users: PaginatedData<UserListItem>;
    statistics: UserStatistics;
    filters: UserFilters;
    roles: Record<string, string>;
    showPendingTab: boolean;
    registrationSettings: RegistrationSettings;
    status?: string;
    error?: string;
};

// ================================================================
// Show page types
// ================================================================

export type UserActivity = {
    id: number;
    description: string;
    event: string | null;
    causer_name: string;
    properties: Record<string, unknown> | null;
    created_at: string | null;
    created_at_human: string | null;
};

export type UsersShowPageProps = {
    user: UserListItem & {
        phone: string | null;
        bio: string | null;
        tagline: string | null;
        gender: string | null;
        birth_date: string | null;
        website_url: string | null;
        twitter_url: string | null;
        facebook_url: string | null;
        instagram_url: string | null;
        linkedin_url: string | null;
        username: string | null;
        updated_at: string | null;
        updated_at_formatted: string | null;
        updated_at_human: string | null;
        last_access: string | null;
        last_access_formatted: string | null;
        last_access_human: string | null;
        email_verified_at_formatted: string | null;
        address1: string | null;
        address2: string | null;
        city: string | null;
        state: string | null;
        country: string | null;
        zip: string | null;
    };
    userActivities: UserActivity[];
    notes: AppNote[];
    noteTarget: NoteTarget;
    noteVisibilityOptions: NoteVisibilityOption[];
    status?: string;
    error?: string;
};

// ================================================================
// Kept for create/edit pages (unchanged)
// ================================================================

export type ManagedUserStatus = 'active' | 'pending' | 'suspended' | 'banned';

export type ManagedUserGender = '' | 'male' | 'female' | 'other';

export type ManagedUserFormValues = {
    name: string;
    first_name: string;
    last_name: string;
    email: string;
    username: string;
    status: ManagedUserStatus;
    password: string;
    password_confirmation: string;
    address1: string;
    address2: string;
    country: string;
    country_code: string;
    state: string;
    state_code: string;
    city: string;
    city_code: string;
    zip: string;
    phone: string;
    birth_date: string;
    gender: ManagedUserGender;
    tagline: string;
    bio: string;
    avatar: File | null;
    website_url: string;
    twitter_url: string;
    facebook_url: string;
    instagram_url: string;
    linkedin_url: string;
    roles: number[];
};

export type ManagedUserListItem = {
    id: number;
    name: string;
    email: string;
    status: ManagedUserStatus;
    email_verified_at: string | null;
    roles: ManagedUserRole[];
};

export type ManagedUserEditingTarget = ManagedUserListItem &
    Omit<ManagedUserFormValues, 'password' | 'password_confirmation' | 'avatar'> & {
        avatar_url: string | null;
    };

export type ManagedUserRoleOption = {
    id: number;
    name: string;
    display_name: string;
    is_system: boolean;
};

export type ManagedUserStatusOption = {
    value: ManagedUserStatus;
    label: string;
};

export type ManagedUserGenderOption = {
    value: ManagedUserGender;
    label: string;
};

export type UserEditPageProps = {
    user: ManagedUserEditingTarget;
    initialValues: ManagedUserFormValues;
    availableRoles: ManagedUserRoleOption[];
    statusOptions: ManagedUserStatusOption[];
    genderOptions: ManagedUserGenderOption[];
};

export type UserCreatePageProps = {
    initialValues: ManagedUserFormValues;
    availableRoles: ManagedUserRoleOption[];
    statusOptions: ManagedUserStatusOption[];
    genderOptions: ManagedUserGenderOption[];
};
