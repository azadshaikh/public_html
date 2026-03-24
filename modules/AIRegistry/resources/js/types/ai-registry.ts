import type { PaginatedData } from '@/types';
import type {
    ScaffoldEmptyStateConfig,
    ScaffoldFilterState,
    ScaffoldInertiaConfig,
    ScaffoldRowActionPayload,
} from '@/types/scaffold';

export type AIRegistryOption = {
    value: string | number;
    label: string;
};

export type AIRegistryIndexPageProps<T> = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<T>;
    filters: ScaffoldFilterState;
    statistics: Record<string, number>;
    empty_state_config?: ScaffoldEmptyStateConfig | null;
};

export type AIRegistryActionPayload = ScaffoldRowActionPayload;

export type AIRegistryRecordSummary = {
    id: number;
    name: string;
    slug: string;
};

export type AIRegistryBadge = {
    value: string;
    label: string;
    class?: string;
};

export type AiProviderListItem = {
    id: number;
    slug: string;
    name: string;
    docs_url: string | null;
    api_key_url: string | null;
    capabilities: AIRegistryBadge[];
    models_count: number;
    is_active: boolean;
    is_active_label: string;
    is_trashed?: boolean;
    edit_url: string;
    actions?: Record<string, AIRegistryActionPayload>;
};

export type AiProviderFormValues = {
    slug: string;
    name: string;
    docs_url: string;
    api_key_url: string;
    capabilities: string[];
    is_active: boolean;
};

export type AiProviderCreatePageProps = {
    initialValues: AiProviderFormValues;
    capabilityOptions: AIRegistryOption[];
};

export type AiProviderEditPageProps = AiProviderCreatePageProps & {
    aiProvider: AIRegistryRecordSummary;
};

export type AiModelListItem = {
    id: number;
    slug: string;
    name: string;
    provider_name: string;
    context_window_formatted: string;
    input_cost_per_1m: string;
    output_cost_per_1m: string;
    is_active: boolean;
    is_active_label: string;
    is_moderated: boolean | null;
    is_moderated_label: string;
    is_trashed?: boolean;
    edit_url: string;
    actions?: Record<string, AIRegistryActionPayload>;
};

export type AiModelFormValues = {
    provider_id: string;
    slug: string;
    name: string;
    description: string;
    context_window: string;
    max_output_tokens: string;
    input_cost_per_1m: string;
    output_cost_per_1m: string;
    input_modalities: string[];
    output_modalities: string[];
    tokenizer: string;
    is_moderated: boolean;
    supported_parameters: string;
    capabilities: string[];
    categories: string[];
    is_active: boolean;
};

export type AiModelCreatePageProps = {
    initialValues: AiModelFormValues;
    providerOptions: AIRegistryOption[];
    capabilityOptions: AIRegistryOption[];
    categoryOptions: AIRegistryOption[];
    inputModalityOptions: AIRegistryOption[];
    outputModalityOptions: AIRegistryOption[];
};

export type AiModelEditPageProps = AiModelCreatePageProps & {
    aiModel: AIRegistryRecordSummary & {
        provider_name?: string;
    };
};