import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldFilterState, ScaffoldInertiaConfig } from '@/types/scaffold';

// ================================================================
// Option type
// ================================================================

export type CustomerOption = {
    value: string;
    label: string;
};

// ================================================================
// Customer list item (used in index datagrid)
// ================================================================

export type CustomerListItem = {
    id: number;
    unique_id: string;
    type: string;
    company_name: string | null;
    company_name_display: string;
    contact_name: string | null;
    email: string;
    phone: string;
    tier: string | null;
    status: string;
    customer_group: string | null;
    billing_total_formatted: string;
    status_label: string;
    status_badge: string;
    is_trashed: boolean;
    show_url: string;
    edit_url: string;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

// ================================================================
// Customer statistics
// ================================================================

export type CustomerStatistics = {
    all: number;
    active: number;
    inactive: number;
    trash: number;
};

// ================================================================
// Customer form values
// ================================================================

export type CustomerFormValues = {
    type: string;
    company_name: string;
    contact_first_name: string;
    contact_last_name: string;
    email: string;
    phone: string;
    phone_code: string;
    billing_email: string;
    billing_phone: string;
    tax_id: string;
    website: string;
    description: string;
    status: string;
    source: string;
    tier: string;
    customer_group: string;
    industry: string;
    org_size: string;
    revenue: string;
    account_manager_id: string;
    currency: string;
    language: string;
    tags: string;
    opt_in_marketing: boolean;
    do_not_call: boolean;
    do_not_email: boolean;
    next_action_date: string;
    user_action: string;
    user_id: string;
    user_password: string;
    user_password_confirmation: string;
};

// ================================================================
// Customer show detail
// ================================================================

export type CustomerShowDetail = {
    id: number;
    unique_id: string;
    type: string;
    company_name: string | null;
    company_name_display: string;
    contact_first_name: string | null;
    contact_last_name: string | null;
    email: string;
    phone: string;
    phone_code: string | null;
    billing_email: string | null;
    billing_phone: string | null;
    website: string | null;
    tax_id: string | null;
    description: string | null;
    status: string;
    status_label: string;
    tier: string | null;
    tier_label: string | null;
    source: string | null;
    source_label: string | null;
    customer_group: string | null;
    customer_group_label: string | null;
    industry_name: string | null;
    org_size: string | null;
    org_size_label: string | null;
    revenue: string | null;
    revenue_label: string | null;
    account_manager_name: string | null;
    user: {
        id: number;
        name: string;
        email: string;
        status: string;
    } | null;
    contacts: {
        id: number;
        full_name: string;
        email: string;
        phone: string | null;
        position: string | null;
        is_primary: boolean;
        status: string;
    }[];
    addresses: {
        id: number;
        type: string;
        is_primary: boolean;
        address1: string | null;
        address2: string | null;
        city: string | null;
        state: string | null;
        zip: string | null;
        country: string | null;
    }[];
    tags: string[] | null;
    opt_in_marketing: boolean;
    do_not_call: boolean;
    do_not_email: boolean;
    language: string | null;
    currency: string | null;
    last_contacted_at: string | null;
    next_action_date: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    is_trashed: boolean;
};

export type CustomerActivity = {
    id: number;
    description: string;
    causer_name: string;
    created_at: string | null;
};

// ================================================================
// Customer page props
// ================================================================

export type CustomerIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<CustomerListItem>;
    filters: ScaffoldFilterState;
    statistics: CustomerStatistics;
};

export type CustomerFormOptions = {
    initialValues: CustomerFormValues;
    typeOptions: CustomerOption[];
    statusOptions: CustomerOption[];
    sourceOptions: CustomerOption[];
    tierOptions: CustomerOption[];
    groupOptions: CustomerOption[];
    industryOptions: CustomerOption[];
    accountManagerOptions: CustomerOption[];
    languageOptions: CustomerOption[];
    orgSizeOptions: CustomerOption[];
    annualRevenueOptions: CustomerOption[];
    userOptions: CustomerOption[];
};

export type CustomerCreatePageProps = CustomerFormOptions;

export type CustomerEditPageProps = CustomerFormOptions & {
    customer: { id: number; name: string };
};

export type CustomerShowPageProps = {
    customer: CustomerShowDetail;
    customerSummary: Record<string, unknown>;
    activities: CustomerActivity[];
};

// ================================================================
// Customer Contact list item
// ================================================================

export type CustomerContactListItem = {
    id: number;
    customer_name: string | null;
    full_name: string;
    email: string;
    phone: string | null;
    is_primary: boolean;
    status: string;
    status_label: string;
    status_badge: string;
    is_trashed: boolean;
    show_url: string;
    edit_url: string;
    customer_show_url: string | null;
    created_at_formatted: string | null;
};

// ================================================================
// Customer Contact statistics
// ================================================================

export type CustomerContactStatistics = {
    all: number;
    active: number;
    inactive: number;
    trash: number;
};

// ================================================================
// Customer Contact form values
// ================================================================

export type CustomerContactFormValues = {
    customer_id: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    phone_code: string;
    position: string;
    is_primary: boolean;
    status: string;
};

// ================================================================
// Customer Contact show detail
// ================================================================

export type CustomerContactShowDetail = {
    id: number;
    first_name: string;
    last_name: string | null;
    full_name: string;
    email: string;
    phone: string | null;
    phone_code: string | null;
    position: string | null;
    is_primary: boolean;
    status: string;
    status_label: string;
    customer_name: string | null;
    customer_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    is_trashed: boolean;
};

// ================================================================
// Customer Contact page props
// ================================================================

export type CustomerContactIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<CustomerContactListItem>;
    filters: ScaffoldFilterState;
    statistics: CustomerContactStatistics;
};

export type CustomerContactFormOptions = {
    initialValues: CustomerContactFormValues;
    statusOptions: CustomerOption[];
    customerOptions: CustomerOption[];
};

export type CustomerContactCreatePageProps = CustomerContactFormOptions;

export type CustomerContactEditPageProps = CustomerContactFormOptions & {
    contact: { id: number; name: string };
};

export type CustomerContactShowPageProps = {
    contact: CustomerContactShowDetail;
};
