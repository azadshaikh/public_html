import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldInertiaConfig } from '@/types/scaffold';

export type EmailOption = {
    value: string;
    label: string;
};

export type EmailProviderListItem = {
    id: number;
    name: string;
    sender_name: string | null;
    sender_email: string | null;
    smtp_host: string | null;
    smtp_encryption: string | null;
    status: string;
    status_label: string;
    status_badge: string;
    order: number;
    show_url: string;
    is_trashed: boolean;
    updated_at: string | null;
};

export type EmailProviderShowItem = EmailProviderListItem & {
    description: string | null;
    smtp_user: string | null;
    smtp_port: string | null;
    reply_to: string | null;
    bcc: string | null;
    signature: string | null;
    created_at: string | null;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
    smtp_encryption_label: string;
    has_smtp_password: boolean;
    deleted_at: string | null;
    deleted_at_formatted: string | null;
    created_by_name?: string;
    updated_by_name?: string;
};

export type EmailProviderStatistics = {
    total: number;
    active: number;
    inactive: number;
    trash: number;
};

export type EmailProviderFilters = {
    search: string;
    created_at: string;
    status: 'all' | 'active' | 'inactive' | 'trash';
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type EmailProviderFormValues = {
    name: string;
    description: string;
    sender_name: string;
    sender_email: string;
    smtp_host: string;
    smtp_user: string;
    smtp_password: string;
    smtp_port: string;
    smtp_encryption: string;
    reply_to: string;
    bcc: string;
    signature: string;
    status: 'active' | 'inactive';
    order: string;
};

export type EmailProviderEditTarget = {
    id: number;
    name: string;
    sender_email: string;
    status: 'active' | 'inactive';
};

export type EmailProvidersIndexPageProps = {
    config: ScaffoldInertiaConfig;
    emailProviders: PaginatedData<EmailProviderListItem>;
    statistics: EmailProviderStatistics;
    filters: EmailProviderFilters;
    status?: string;
    error?: string;
};

export type EmailProviderCreatePageProps = {
    initialValues: EmailProviderFormValues;
    statusOptions: EmailOption[];
    encryptionOptions: EmailOption[];
};

export type EmailProviderEditPageProps = EmailProviderCreatePageProps & {
    emailProvider: EmailProviderEditTarget;
};

export type EmailProviderShowPageProps = {
    emailProvider: EmailProviderShowItem;
    status?: string;
    error?: string;
};

export type EmailTemplateListItem = {
    id: number;
    name: string;
    subject: string;
    message: string;
    send_to: string | null;
    provider: {
        id: number | null;
        name: string | null;
    } | null;
    provider_name: string;
    status: string;
    status_label: string;
    status_badge: string;
    is_raw: boolean;
    template_info: string;
    show_url: string;
    is_trashed: boolean;
    created_at: string | null;
    created_at_formatted: string | null;
    updated_at: string | null;
    updated_at_formatted: string | null;
};

export type EmailTemplateShowItem = EmailTemplateListItem & {
    send_to_list: string[];
    deleted_at: string | null;
    deleted_at_formatted: string | null;
    created_by_name?: string;
    updated_by_name?: string;
};

export type EmailTemplateStatistics = {
    total: number;
    active: number;
    inactive: number;
    trash: number;
};

export type EmailTemplateFilters = {
    search: string;
    provider_id: string;
    created_at: string;
    status: 'all' | 'active' | 'inactive' | 'trash';
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type EmailTemplateFormValues = {
    name: string;
    subject: string;
    message: string;
    send_to: string;
    provider_id: string;
    is_raw: boolean;
    status: 'active' | 'inactive';
};

export type EmailTemplateEditTarget = {
    id: number;
    name: string;
    subject: string;
    status: 'active' | 'inactive';
    provider_id: string;
};

export type EmailTemplatesIndexPageProps = {
    config: ScaffoldInertiaConfig;
    emailTemplates: PaginatedData<EmailTemplateListItem>;
    statistics: EmailTemplateStatistics;
    providerOptions: EmailOption[];
    filters: EmailTemplateFilters;
    status?: string;
    error?: string;
};

export type EmailTemplateCreatePageProps = {
    initialValues: EmailTemplateFormValues;
    statusOptions: EmailOption[];
    providerOptions: EmailOption[];
};

export type EmailTemplateEditPageProps = EmailTemplateCreatePageProps & {
    emailTemplate: EmailTemplateEditTarget;
};

export type EmailTemplateShowPageProps = {
    emailTemplate: EmailTemplateShowItem;
    status?: string;
    error?: string;
};

export type EmailLogListItem = {
    id: number;
    show_url: string;
    subject: string;
    template_name: string | null;
    template: {
        id: number;
        name: string;
    } | null;
    provider_name: string | null;
    provider: {
        id: number;
        name: string;
    } | null;
    status: string;
    status_label: string;
    status_badge: string;
    recipients: string[];
    error_message: string | null;
    sent_at: string | null;
    sent_at_formatted: string | null;
    created_at: string | null;
    created_at_formatted: string | null;
};

export type EmailLogShowItem = EmailLogListItem & {
    body: string;
    context: Record<string, unknown> | unknown[];
    sender_name: string | null;
};

export type EmailLogStatistics = {
    total: number;
    sent: number;
    failed: number;
    queued: number;
};

export type EmailLogFilters = {
    search: string;
    email_provider_id: string;
    email_template_id: string;
    sent_at: string;
    status: 'all' | 'sent' | 'failed' | 'queued';
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type EmailLogsIndexPageProps = {
    config: ScaffoldInertiaConfig;
    emailLogs: PaginatedData<EmailLogListItem>;
    statistics: EmailLogStatistics;
    providerOptions: EmailOption[];
    templateOptions: EmailOption[];
    filters: EmailLogFilters;
    status?: string;
    error?: string;
};

export type EmailLogShowPageProps = {
    emailLog: EmailLogShowItem;
    status?: string;
    error?: string;
};
