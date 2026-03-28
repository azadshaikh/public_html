export type DraftMenuItem = {
    id: number;
    parent_id: number;
    title: string;
    url: string;
    type: string;
    target: string;
    icon: string;
    css_classes: string;
    link_title: string;
    link_rel: string;
    description: string;
    object_id: number | null;
    sort_order: number;
    is_active: boolean;
};

export type MenuSettings = {
    name: string;
    location: string;
    is_active: boolean;
    description: string;
};

export type RenderItem = {
    item: DraftMenuItem;
    depth: number;
};

export type SavePayload = {
    settings: MenuSettings;
    items: {
        new: DraftMenuItem[];
        updated: DraftMenuItem[];
        deleted: { id: number }[];
        order: { id: number; parent_id: number; sort_order: number }[];
    };
};

export type SaveResponse = {
    success: boolean;
    message: string;
    details?: string;
    errors?: Record<string, string[]>;
    newItemIds: Record<string, number>;
};
