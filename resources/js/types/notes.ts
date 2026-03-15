export type NoteBadgeVariant =
    | 'default'
    | 'secondary'
    | 'success'
    | 'warning'
    | 'info'
    | 'danger'
    | 'destructive'
    | 'outline';

export type NoteVisibilityValue = 'private' | 'team' | 'customer';
export type NoteTypeValue = 'note' | 'system';

export type NoteTarget = {
    type: string;
    id: number;
};

export type NoteVisibilityOption = {
    value: NoteVisibilityValue;
    label: string;
    description: string;
};

export type AppNote = {
    id: number;
    content: string;
    content_html: string;
    excerpt: string;
    is_pinned: boolean;
    is_system: boolean;
    is_private: boolean;
    is_editable: boolean;
    is_deletable: boolean;
    type: {
        value: NoteTypeValue;
        label: string;
        badge: NoteBadgeVariant;
    };
    visibility: {
        value: NoteVisibilityValue;
        label: string;
        description: string;
        badge: NoteBadgeVariant;
    };
    author: {
        id: number;
        name: string;
        avatar_url: string | null;
    } | null;
    actions: {
        update: string;
        destroy: string;
        toggle_pin: string;
    };
    created_at: string | null;
    created_at_formatted: string | null;
    created_at_human: string | null;
    updated_at: string | null;
    updated_at_formatted: string | null;
    updated_at_human: string | null;
    pinned_at: string | null;
    pinned_at_formatted: string | null;
    pinned_at_human: string | null;
};
