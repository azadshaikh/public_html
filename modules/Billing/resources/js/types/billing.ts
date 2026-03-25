import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldFilterState, ScaffoldInertiaConfig } from '@/types/scaffold';

// ================================================================
// Shared option type
// ================================================================

export type BillingOption = {
    value: string | number;
    label: string;
};

// ================================================================
// Invoice
// ================================================================

export type InvoiceLineItem = {
    id: number | null;
    name: string;
    description: string;
    quantity: number;
    unit_price: number;
    tax_rate: number;
    discount_rate: number;
    sort_order: number;
};

export type InvoiceListItem = {
    id: number;
    show_url: string;
    invoice_number: string;
    reference: string | null;
    customer_display: string;
    billing_email: string | null;
    billing_phone: string | null;
    subtotal: number;
    tax_amount: number;
    discount_amount: number;
    total: number;
    amount_paid: number;
    amount_due: number;
    formatted_total: string;
    formatted_amount_due: string;
    currency: string;
    exchange_rate: number;
    issue_date: string | null;
    due_date: string | null;
    paid_at: string | null;
    status: string;
    status_label: string;
    status_badge: string;
    payment_status: string;
    payment_status_label: string;
    payment_status_badge: string;
    is_trashed: boolean;
    actions?: { key: string; label: string; url?: string; method?: string; confirm?: string; variant?: string }[];
};

export type InvoiceFormValues = {
    invoice_number: string;
    reference: string;
    customer_id: string | number;
    billing_name: string;
    billing_email: string;
    billing_phone: string;
    billing_address: string;
    currency: string;
    exchange_rate: number;
    issue_date: string;
    due_date: string;
    status: string;
    payment_status: string;
    paid_at: string;
    notes: string;
    terms: string;
    items: InvoiceLineItem[];
};

export type InvoiceShowDetail = InvoiceListItem & {
    billing_name: string | null;
    billing_address: string | null;
    notes: string | null;
    terms: string | null;
    items: InvoiceLineItem[];
    created_at: string | null;
    created_at_formatted: string | null;
    updated_at: string | null;
    updated_at_formatted: string | null;
};

export type InvoiceIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<InvoiceListItem>;
    filters: ScaffoldFilterState;
    statistics?: Record<string, number>;
};

export type InvoiceCreatePageProps = {
    initialValues: InvoiceFormValues;
    statusOptions: BillingOption[];
    paymentStatusOptions: BillingOption[];
    currencyOptions: BillingOption[];
    customerOptions: BillingOption[];
};

export type InvoiceEditPageProps = InvoiceCreatePageProps & {
    invoice: { id: number; name: string };
};

export type InvoiceShowPageProps = {
    invoice: InvoiceShowDetail;
};

// ================================================================
// Payment
// ================================================================

export type PaymentListItem = {
    id: number;
    show_url: string;
    payment_number: string;
    reference: string | null;
    invoice_id: number | null;
    invoice_number: string | null;
    customer_display: string;
    amount: number;
    formatted_amount: string;
    currency: string;
    exchange_rate: number;
    payment_method: string;
    payment_method_label: string;
    payment_method_badge: string;
    payment_gateway: string;
    status: string;
    status_label: string;
    status_badge: string;
    paid_at: string | null;
    failed_at: string | null;
    is_trashed: boolean;
    actions?: { key: string; label: string; url?: string; method?: string; confirm?: string; variant?: string }[];
};

export type PaymentFormValues = {
    payment_number: string;
    reference: string;
    idempotency_key: string;
    invoice_id: string | number;
    customer_id: string | number;
    amount: string | number;
    currency: string;
    exchange_rate: number;
    payment_method: string;
    payment_gateway: string;
    status: string;
    gateway_transaction_id: string;
    paid_at: string;
    failed_at: string;
    notes: string;
};

export type PaymentShowDetail = PaymentListItem & {
    idempotency_key: string | null;
    gateway_transaction_id: string | null;
    notes: string | null;
    created_at: string | null;
    created_at_formatted: string | null;
    updated_at: string | null;
    updated_at_formatted: string | null;
};

export type PaymentIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<PaymentListItem>;
    filters: ScaffoldFilterState;
    statistics?: Record<string, number>;
};

export type PaymentCreatePageProps = {
    initialValues: PaymentFormValues;
    statusOptions: BillingOption[];
    methodOptions: BillingOption[];
    gatewayOptions: BillingOption[];
    currencyOptions: BillingOption[];
    customerOptions: BillingOption[];
    invoiceOptions: BillingOption[];
};

export type PaymentEditPageProps = PaymentCreatePageProps & {
    payment: { id: number; name: string };
};

export type PaymentShowPageProps = {
    payment: PaymentShowDetail;
};

// ================================================================
// Credit
// ================================================================

export type CreditListItem = {
    id: number;
    show_url: string;
    credit_number: string;
    reference: string | null;
    invoice_id: number | null;
    customer_display: string;
    amount: number;
    amount_used: number;
    amount_remaining: number;
    formatted_amount: string;
    formatted_remaining: string;
    currency: string;
    type: string;
    type_label: string;
    type_badge: string;
    status: string;
    status_label: string;
    status_badge: string;
    expires_at: string | null;
    is_trashed: boolean;
    actions?: { key: string; label: string; url?: string; method?: string; confirm?: string; variant?: string }[];
};

export type CreditFormValues = {
    credit_number: string;
    reference: string;
    customer_id: string | number;
    invoice_id: string | number;
    amount: string | number;
    amount_used: number;
    amount_remaining: string | number;
    currency: string;
    type: string;
    status: string;
    expires_at: string;
    reason: string;
    notes: string;
};

export type CreditShowDetail = CreditListItem & {
    reason: string | null;
    notes: string | null;
    created_at: string | null;
    created_at_formatted: string | null;
    updated_at: string | null;
    updated_at_formatted: string | null;
};

export type CreditIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<CreditListItem>;
    filters: ScaffoldFilterState;
    statistics?: Record<string, number>;
};

export type CreditCreatePageProps = {
    initialValues: CreditFormValues;
    statusOptions: BillingOption[];
    typeOptions: BillingOption[];
    currencyOptions: BillingOption[];
    customerOptions: BillingOption[];
    invoiceOptions: BillingOption[];
};

export type CreditEditPageProps = CreditCreatePageProps & {
    credit: { id: number; name: string };
};

export type CreditShowPageProps = {
    credit: CreditShowDetail;
};

// ================================================================
// Refund
// ================================================================

export type RefundListItem = {
    id: number;
    show_url: string;
    refund_number: string;
    reference: string | null;
    payment_id: number;
    payment_number: string | null;
    invoice_id: number | null;
    invoice_number: string | null;
    customer_display: string;
    amount: number;
    formatted_amount: string;
    currency: string;
    type: string;
    type_label: string;
    type_badge: string;
    status: string;
    status_label: string;
    status_badge: string;
    refunded_at: string | null;
    failed_at: string | null;
    is_trashed: boolean;
    actions?: { key: string; label: string; url?: string; method?: string; confirm?: string; variant?: string }[];
};

export type RefundFormValues = {
    refund_number: string;
    reference: string;
    idempotency_key: string;
    payment_id: string | number;
    invoice_id: string | number;
    customer_id: string | number;
    amount: string | number;
    currency: string;
    type: string;
    status: string;
    gateway_refund_id: string;
    refunded_at: string;
    failed_at: string;
    reason: string;
    notes: string;
};

export type RefundShowDetail = RefundListItem & {
    idempotency_key: string | null;
    gateway_refund_id: string | null;
    reason: string | null;
    notes: string | null;
    created_at: string | null;
    created_at_formatted: string | null;
    updated_at: string | null;
    updated_at_formatted: string | null;
};

export type RefundIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<RefundListItem>;
    filters: ScaffoldFilterState;
    statistics?: Record<string, number>;
};

export type RefundCreatePageProps = {
    initialValues: RefundFormValues;
    statusOptions: BillingOption[];
    typeOptions: BillingOption[];
    currencyOptions: BillingOption[];
    customerOptions: BillingOption[];
    paymentOptions: BillingOption[];
    invoiceOptions: BillingOption[];
};

export type RefundEditPageProps = RefundCreatePageProps & {
    refund: { id: number; name: string };
};

export type RefundShowPageProps = {
    refund: RefundShowDetail;
};

// ================================================================
// Tax
// ================================================================

export type TaxListItem = {
    id: number;
    show_url: string;
    name: string;
    code: string;
    type: string;
    rate: number;
    formatted_rate: string;
    country: string | null;
    state: string | null;
    is_active: boolean;
    is_compound: boolean;
    status: string;
    status_label: string;
    status_badge: string;
    is_trashed: boolean;
    actions?: { key: string; label: string; url?: string; method?: string; confirm?: string; variant?: string }[];
};

export type TaxFormValues = {
    name: string;
    code: string;
    type: string;
    rate: string | number;
    country: string;
    state: string;
    postal_code: string;
    description: string;
    is_compound: boolean;
    priority: string | number;
    is_active: boolean;
    effective_from: string;
    effective_to: string;
};

export type TaxShowDetail = TaxListItem & {
    description: string | null;
    postal_code: string | null;
    priority: number;
    effective_from: string | null;
    effective_to: string | null;
    created_at: string | null;
    created_at_formatted: string | null;
    updated_at: string | null;
    updated_at_formatted: string | null;
};

export type TaxIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<TaxListItem>;
    filters: ScaffoldFilterState;
    statistics?: Record<string, number>;
};

export type TaxCreatePageProps = {
    initialValues: TaxFormValues;
    typeOptions: BillingOption[];
    countryOptions: BillingOption[];
    stateOptions: BillingOption[];
};

export type TaxEditPageProps = TaxCreatePageProps & {
    tax: { id: number; name: string };
};

export type TaxShowPageProps = {
    tax: TaxShowDetail;
};

// ================================================================
// Coupon
// ================================================================

export type CouponListItem = {
    id: number;
    show_url: string;
    code: string;
    name: string;
    description: string | null;
    type: string;
    type_label: string;
    type_badge: string;
    value: number;
    value_display: string;
    currency: string | null;
    discount_duration: string;
    discount_duration_label: string;
    discount_duration_badge: string;
    duration_in_months: number | null;
    max_uses: number | null;
    max_uses_display: string | number;
    uses_count: number;
    max_uses_per_customer: number;
    min_order_amount: number | null;
    applicable_plan_ids: number[];
    expires_at: string | null;
    expires_at_display: string;
    is_active: boolean;
    is_active_label: string;
    is_active_badge: string;
    created_at: string | null;
    is_trashed: boolean;
    actions?: { key: string; label: string; url?: string; method?: string; confirm?: string; variant?: string }[];
};

export type CouponFormValues = {
    code: string;
    name: string;
    description: string;
    type: string;
    value: string | number;
    currency: string;
    discount_duration: string;
    duration_in_months: string | number;
    max_uses: string | number;
    max_uses_per_customer: string | number;
    min_order_amount: string | number;
    applicable_plan_ids: number[];
    expires_at: string;
    is_active: boolean;
};

export type CouponShowDetail = CouponListItem & {
    created_at: string | null;
    created_at_formatted: string | null;
    updated_at: string | null;
    updated_at_formatted: string | null;
};

export type CouponIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<CouponListItem>;
    filters: ScaffoldFilterState;
    statistics?: Record<string, number>;
};

export type CouponCreatePageProps = {
    initialValues: CouponFormValues;
    typeOptions: BillingOption[];
    durationOptions: BillingOption[];
    planOptions: BillingOption[];
};

export type CouponEditPageProps = CouponCreatePageProps & {
    coupon: { id: number; name: string };
};

export type CouponShowPageProps = {
    coupon: CouponShowDetail;
};

// ================================================================
// Transaction (read-only)
// ================================================================

export type TransactionListItem = {
    id: number;
    show_url: string;
    transaction_id: string;
    reference: string | null;
    customer_display: string;
    source_display: string;
    amount: number;
    formatted_amount: string;
    currency: string;
    type: string;
    type_label: string;
    type_badge: string;
    payment_method: string | null;
    payment_method_label: string;
    payment_method_badge: string;
    status: string;
    status_label: string;
    status_badge: string;
    created_at: string | null;
    actions?: { key: string; label: string; url?: string; method?: string; confirm?: string; variant?: string }[];
};

export type TransactionShowDetail = TransactionListItem & {
    balance_before: number;
    balance_after: number;
    description: string | null;
    gateway_transaction_id: string | null;
    payment_gateway: string | null;
};

export type TransactionIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<TransactionListItem>;
    filters: ScaffoldFilterState;
    statistics?: Record<string, number>;
};

export type TransactionShowPageProps = {
    transaction: TransactionShowDetail;
};

// ================================================================
// Settings
// ================================================================

export type InvoiceSettings = {
    invoice_prefix: string;
    invoice_serial_number: number;
    invoice_digit_length: number;
    invoice_format: string;
};

export type StripeSettings = {
    stripe_key: string;
    stripe_secret: string;
    stripe_webhook_secret: string;
};

export type SettingsPageProps = {
    section: string;
    invoiceSettings: InvoiceSettings;
    stripeSettings: StripeSettings;
    invoiceDigitLengthOptions: BillingOption[];
    invoiceFormatOptions: BillingOption[];
};
