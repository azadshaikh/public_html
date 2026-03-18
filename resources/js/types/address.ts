import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldInertiaConfig } from '@/types/scaffold';

export type AddressRowAction = {
    url: string;
    label: string;
    icon: string;
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    confirm?: string;
    fullReload?: boolean;
};

export type AddressListItem = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    company: string | null;
    full_name: string | null;
    type: string;
    type_label: string;
    type_class: string;
    address1: string;
    address2: string | null;
    address3: string | null;
    city: string;
    city_code: string | null;
    state: string | null;
    state_code: string | null;
    country_code: string;
    country_name: string | null;
    zip: string | null;
    phone: string | null;
    phone_code: string | null;
    latitude: string | null;
    longitude: string | null;
    is_primary: boolean;
    is_verified: boolean;
    primary_label: string;
    primary_class: string;
    verified_label: string;
    verified_class: string;
    addressable_type: string | null;
    addressable_id: number | null;
    addressable_label: string | null;
    show_url: string;
    edit_url: string;
    full_address: string;
    formatted_address: string;
    has_coordinates: boolean;
    created_at: string;
    created_at_formatted: string;
    created_at_human: string;
    updated_at: string | null;
    updated_at_formatted: string | null;
    deleted_at: string | null;
};

export type AddressShowItem = AddressListItem & {
    is_trashed: boolean;
};

export type AddressStatistics = {
    total: number;
    trash: number;
};

export type AddressFilters = {
    search: string;
    type: string;
    country_code: string;
    is_primary: string;
    is_verified: string;
    created_at: string;
    status: 'all' | 'trash';
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type AddressTypeOption = {
    value: string;
    label: string;
};

export type AddressFormValues = {
    first_name: string;
    last_name: string;
    company: string;
    type: string;
    address1: string;
    address2: string;
    address3: string;
    country: string;
    country_code: string;
    state: string;
    state_code: string;
    city: string;
    city_code: string;
    zip: string;
    phone: string;
    phone_code: string;
    latitude: string;
    longitude: string;
    is_primary: boolean;
    is_verified: boolean;
    addressable_type: string;
    addressable_id: string;
};

export type AddressEditTarget = {
    id: number;
    full_name: string | null;
    type: string;
    city: string;
    country_code: string;
};

export type AddressIndexPageProps = {
    config: ScaffoldInertiaConfig;
    addresses: PaginatedData<AddressListItem>;
    statistics: AddressStatistics;
    filters: AddressFilters;
    status?: string;
    error?: string;
};

export type AddressCreatePageProps = {
    initialValues: AddressFormValues;
    typeOptions: AddressTypeOption[];
};

export type AddressEditPageProps = {
    address: AddressEditTarget;
    initialValues: AddressFormValues;
    typeOptions: AddressTypeOption[];
};

export type AddressShowPageProps = {
    address: AddressShowItem;
    status?: string;
    error?: string;
};
