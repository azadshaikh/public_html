import type { ScaffoldIndexPageProps } from '@/types/scaffold';

// ================================================================
// SHARED
// ================================================================

export type OrderOption = {
    value: string | number;
    label: string;
    example?: string;
};

// ================================================================
// ORDERS
// ================================================================

export type OrderListItem = {
    id: number;
    order_number: string;
    show_url: string;
    customer_display: string;
    type: string;
    type_label: string;
    type_badge: string;
    status: string;
    status_label: string;
    status_badge: string;
    total_display: string;
    paid_at_formatted: string | null;
    is_trashed: boolean;
    actions?: Record<string, unknown>;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

export type OrderItem = {
    id: number;
    order_id: number;
    plan_id: number | null;
    name: string;
    description: string | null;
    quantity: number;
    unit_price: number | string;
    total: number | string;
};

export type OrderShowDetail = {
    id: number;
    order_number: string;
    customer_id: number | null;
    customer_display: string;
    type: string;
    type_label: string;
    type_badge: string;
    status: string;
    status_label: string;
    status_badge: string;
    subtotal: number | string;
    subtotal_display: string;
    discount_amount: number | string;
    discount_display: string;
    tax_amount: number | string;
    tax_display: string;
    total: number | string;
    total_display: string;
    currency: string;
    coupon_code: string | null;
    notes: string | null;
    paid_at: string | null;
    paid_at_formatted: string | null;
    created_at_formatted: string | null;
    items: OrderItem[];
    customer?: {
        id: number;
        company_name: string | null;
        contact_first_name: string | null;
        contact_last_name: string | null;
        email: string | null;
    };
    is_trashed: boolean;
};

export type OrderIndexPageProps = ScaffoldIndexPageProps<OrderListItem>;

export type OrderShowPageProps = {
    order: OrderShowDetail;
};

// ================================================================
// ORDER SETTINGS
// ================================================================

export type OrderSettingsValues = {
    order_prefix: string;
    order_serial_number: number;
    order_digit_length: number;
    order_format: string;
};

export type OrderSettingsPageProps = {
    initialValues: OrderSettingsValues;
    digitLengthOptions: OrderOption[];
    formatOptions: OrderOption[];
};
