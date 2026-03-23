import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldFilterState, ScaffoldInertiaConfig } from '@/types/scaffold';

// ================================================================
// Option type
// ================================================================

export type HelpdeskOption = {
    value: string;
    label: string;
};

// ================================================================
// Department list item (used in index datagrid)
// ================================================================

export type DepartmentListItem = {
    id: number;
    name: string;
    description: string | null;
    department_head_name: string;
    visibility: string;
    visibility_label: string;
    visibility_badge: string;
    status_label: string;
    status_badge: string;
    is_trashed: boolean;
    show_url: string;
    edit_url: string;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

// ================================================================
// Department statistics
// ================================================================

export type DepartmentStatistics = {
    all: number;
    active: number;
    inactive: number;
    trash: number;
};

// ================================================================
// Department form values
// ================================================================

export type DepartmentFormValues = {
    name: string;
    description: string;
    department_head: string;
    visibility: string;
    status: string;
};

// ================================================================
// Department show detail
// ================================================================

export type DepartmentShowDetail = {
    id: number;
    name: string;
    description: string;
    department_head_name: string;
    visibility: string;
    visibility_label: string;
    status: string;
    status_label: string;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    is_trashed: boolean;
};

// ================================================================
// Department page props
// ================================================================

export type DepartmentIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<DepartmentListItem>;
    filters: ScaffoldFilterState;
    statistics: DepartmentStatistics;
};

export type DepartmentFormOptions = {
    initialValues: DepartmentFormValues;
    headOptions: HelpdeskOption[];
    visibilityOptions: HelpdeskOption[];
    statusOptions: HelpdeskOption[];
};

export type DepartmentCreatePageProps = DepartmentFormOptions;

export type DepartmentEditPageProps = DepartmentFormOptions & {
    department: { id: number; name: string };
};

export type DepartmentShowPageProps = {
    department: DepartmentShowDetail;
    statistics: {
        tickets: number;
        open_tickets: number;
    };
};

// ================================================================
// Ticket list item (used in index datagrid)
// ================================================================

export type TicketListItem = {
    id: number;
    ticket_number: string;
    subject: string;
    department_name: string;
    raised_by_name: string;
    assigned_to_name: string;
    priority: string;
    priority_label: string;
    priority_badge: string;
    status: string;
    status_label: string;
    status_badge: string;
    is_trashed: boolean;
    show_url: string;
    edit_url: string;
    created_at_formatted: string | null;
    updated_at_formatted: string | null;
};

// ================================================================
// Ticket statistics
// ================================================================

export type TicketStatistics = {
    all: number;
    open: number;
    pending: number;
    resolved: number;
    on_hold: number;
    closed: number;
    cancelled: number;
    trash: number;
};

// ================================================================
// Ticket form values
// ================================================================

export type TicketFormValues = {
    ticket_number: string;
    department_id: string;
    user_id: string;
    subject: string;
    description: string;
    priority: string;
    assigned_to: string;
    status: string;
    attachments: File[] | null;
    attachments_urls: string[];
};

// ================================================================
// Ticket attachment
// ================================================================

export type TicketAttachment = {
    file_name: string;
    file_path: string;
    file_size: number | null;
    mime_type: string | null;
    url: string | null;
};

// ================================================================
// Ticket show detail
// ================================================================

export type TicketShowDetail = {
    id: number;
    ticket_number: string;
    subject: string;
    description: string;
    department_name: string;
    requester_name: string;
    requester_avatar: string | null;
    assigned_to_name: string;
    assigned_to_avatar: string | null;
    priority: string;
    priority_label: string;
    status: string;
    status_label: string;
    opened_at: string | null;
    closed_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    is_trashed: boolean;
    attachments: TicketAttachment[];
};

// ================================================================
// Ticket reply
// ================================================================

export type TicketReply = {
    id: number;
    content: string;
    is_internal: boolean;
    reply_by_name: string;
    reply_by_avatar: string | null;
    created_at: string | null;
    attachments: {
        file_name: string;
        file_path: string;
        url: string | null;
    }[];
};

// ================================================================
// Ticket reply form values
// ================================================================

export type TicketReplyFormValues = {
    department_id: string;
    assigned_to: string;
    priority: string;
    status: string;
    content: string;
    is_internal: boolean;
    attachments: File[] | null;
};

// ================================================================
// Activity log entry
// ================================================================

export type ActivityEntry = {
    id: number;
    description: string;
    created_at: string | null;
    causer_name: string | null;
};

// ================================================================
// Ticket page props
// ================================================================

export type TicketIndexPageProps = {
    config: ScaffoldInertiaConfig;
    rows: PaginatedData<TicketListItem>;
    filters: ScaffoldFilterState;
    statistics: TicketStatistics;
};

export type TicketFormOptions = {
    initialValues: TicketFormValues;
    departments: HelpdeskOption[];
    users: HelpdeskOption[];
    priorityOptions: HelpdeskOption[];
    statusOptions: HelpdeskOption[];
    existingAttachments: TicketAttachment[];
};

export type TicketCreatePageProps = TicketFormOptions;

export type TicketEditPageProps = TicketFormOptions & {
    ticket: {
        id: number;
        ticket_number: string;
        subject: string;
        status: string;
    };
};

export type TicketShowPageProps = {
    ticket: TicketShowDetail;
    replies: TicketReply[];
    replyInitialValues: TicketReplyFormValues;
    activities: ActivityEntry[];
    departments: HelpdeskOption[];
    users: HelpdeskOption[];
    priorityOptions: HelpdeskOption[];
    statusOptions: HelpdeskOption[];
};

// ================================================================
// Settings
// ================================================================

export type HelpdeskSettingsValues = {
    ticket_prefix: string;
    ticket_serial_number: string;
    ticket_digit_length: string;
};

export type HelpdeskSettingsPageProps = {
    initialValues: HelpdeskSettingsValues;
    ticket_length_options: HelpdeskOption[];
};
