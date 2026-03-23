import type { ScaffoldIndexPageProps, ScaffoldRowActionPayload } from '@/types/scaffold';

// ================================================================
// SHARED
// ================================================================

export type SubscriptionOption = {
    value: string | number;
    label: string;
};

// ================================================================
// PLANS
// ================================================================

export type PlanPrice = {
    id: number;
    billing_cycle: string;
    billing_cycle_label: string;
    price: number | string;
    formatted_price: string;
    currency: string;
    is_active: boolean;
    sort_order: number;
};

export type PlanFeature = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    type: string;
    value: string | null;
    sort_order: number;
};

export type PlanListItem = {
    id: number;
    show_url: string;
    code: string;
    name: string;
    description: string | null;
    prices: PlanPrice[];
    prices_summary: string;
    trial_days: number;
    grace_days: number;
    sort_order: number;
    is_popular: boolean;
    is_active: boolean;
    is_active_label: string;
    status_badge: string;
    subscriptions_count: number;
    features_count: number;
    is_trashed: boolean;
    actions?: Record<string, ScaffoldRowActionPayload>;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

export type PlanFormPriceRow = {
    id?: number;
    billing_cycle: string;
    price: string;
    currency: string;
    is_active: boolean;
    sort_order: number;
};

export type PlanFormFeatureRow = {
    id?: number;
    code: string;
    name: string;
    description: string;
    type: string;
    value: string;
    sort_order: number;
};

export type PlanFormValues = {
    code: string;
    name: string;
    description: string;
    prices: PlanFormPriceRow[];
    features: PlanFormFeatureRow[];
    trial_days: number | string;
    grace_days: number | string;
    sort_order: number | string;
    is_popular: boolean;
    is_active: boolean;
};

export type PlanShowDetail = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    prices: PlanPrice[];
    features: PlanFeature[];
    trial_days: number;
    grace_days: number;
    sort_order: number;
    is_popular: boolean;
    is_active: boolean;
    status_label: string;
    status_badge: string;
    formatted_price: string | null;
    billing_cycle_label: string | null;
    subscriptions_count: number;
    is_trashed: boolean;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

export type PlanIndexPageProps = ScaffoldIndexPageProps<PlanListItem>;

export type PlanCreatePageProps = {
    initialValues: PlanFormValues;
    billingCycleOptions: SubscriptionOption[];
    currencyOptions: SubscriptionOption[];
    featureTypeOptions: SubscriptionOption[];
};

export type PlanEditPageProps = PlanCreatePageProps & {
    plan: { id: number; name: string };
};

export type PlanShowPageProps = {
    plan: PlanShowDetail;
};

// ================================================================
// SUBSCRIPTIONS
// ================================================================

export type SubscriptionListItem = {
    id: number;
    unique_id: string;
    show_url: string;
    plan_id: number;
    plan_name: string;
    plan_code: string | null;
    billing_cycle: string;
    price: number | string;
    formatted_price: string;
    currency: string;
    status: string;
    status_label: string;
    status_badge: string;
    subscriber_name: string;
    subscriber_url: string | null;
    trial_ends_at: string | null;
    trial_ends_at_formatted: string | null;
    current_period_start: string | null;
    current_period_start_formatted: string | null;
    current_period_end: string | null;
    current_period_end_formatted: string | null;
    canceled_at: string | null;
    canceled_at_formatted: string | null;
    cancels_at: string | null;
    cancels_at_formatted: string | null;
    on_trial: boolean;
    on_grace_period: boolean;
    is_trashed: boolean;
    actions?: Record<string, ScaffoldRowActionPayload>;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

export type SubscriptionFormValues = {
    customer_id: string;
    plan_id: string;
    plan_price_id: string;
    price: string;
    currency: string;
    status: string;
    trial_days: number;
};

export type SubscriptionShowDetail = {
    id: number;
    unique_id: string;
    customer_id: number;
    plan_id: number;
    plan_price_id: number | null;
    previous_plan_id: number | null;
    status: string;
    status_label: string;
    status_badge: string;
    billing_cycle: string;
    price: number | string;
    formatted_price: string;
    currency: string;
    subscriber_name: string;
    subscriber_url: string | null;
    plan_name: string;
    plan_code: string | null;
    trial_ends_at: string | null;
    trial_ends_at_formatted: string | null;
    current_period_start: string | null;
    current_period_start_formatted: string | null;
    current_period_end: string | null;
    current_period_end_formatted: string | null;
    canceled_at: string | null;
    canceled_at_formatted: string | null;
    cancels_at: string | null;
    cancels_at_formatted: string | null;
    plan_changed_at: string | null;
    ended_at: string | null;
    paused_at: string | null;
    resumes_at: string | null;
    on_trial: boolean;
    on_grace_period: boolean;
    cancel_at_period_end: boolean;
    is_trashed: boolean;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

export type SubscriptionIndexPageProps = ScaffoldIndexPageProps<SubscriptionListItem>;

export type SubscriptionCreatePageProps = {
    initialValues: SubscriptionFormValues;
    planOptions: SubscriptionOption[];
    planPriceOptionsByPlan: Record<string, SubscriptionOption[]>;
    statusOptions: SubscriptionOption[];
    customerOptions: SubscriptionOption[];
};

export type SubscriptionEditPageProps = SubscriptionCreatePageProps & {
    subscription: { id: number; name: string };
};

export type SubscriptionShowPageProps = {
    subscription: SubscriptionShowDetail;
};
