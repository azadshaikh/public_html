import type { PaginatedData } from '@/types/pagination';
import type { ScaffoldInertiaConfig } from '@/types/scaffold';

// =========================================================================
// Media list item (from MediaLibraryResource)
// =========================================================================

export type MediaListItem = {
    id: number;
    checkbox: boolean;
    file_name: string;
    name: string;
    mime_type: string;
    size: number;
    human_readable_size: string;
    mime_type_label: string;
    mime_type_class: string;
    thumbnail_url: string | null;
    original_url: string | null;
    media_url: string | null;
    owner: string | null;
    owner_id: number | null;
    alt_text: string;
    caption: string;
    description: string;
    tags: string;
    is_trashed: boolean;
    is_processing: boolean;
    conversion_status: 'completed' | 'processing' | 'not_applicable';
    show_url: string;
    edit_url: string;
    delete_url: string;
    restore_url: string;
    created_at: string;
    updated_at: string;
};

// =========================================================================
// Upload types
// =========================================================================

export type UploadSettings = {
    max_size_mb: number;
    max_size_bytes: number;
    max_files_per_upload: number;
    accepted_mime_types: string;
    friendly_file_types: string;
    max_filename_length: number;
    upload_route: string;
};

// =========================================================================
// Media picker filters (shared by dialog + pages using HasMediaPicker)
// =========================================================================

export type MediaPickerFilters = {
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    mime_type_category: string;
    picker: string;
    view: 'table' | 'cards';
    status?: 'all' | 'trash';
};

export type StorageData = {
    used_size_bytes: number;
    used_size_readable: string;
    max_size_bytes: number;
    max_size_readable: string;
    percentage_used: number;
    remaining_bytes: number | null;
    remaining_readable: string;
};

export type UploadFileStatus =
    | 'staged'
    | 'pending'
    | 'uploading'
    | 'processing'
    | 'success'
    | 'error';

export type UploadFile = {
    id: string;
    file: File;
    name: string;
    size: number;
    type: string;
    progress: number;
    status: UploadFileStatus;
    error?: string;
    mediaId?: number;
    thumbnailUrl?: string;
    previewUrl?: string;
};

export type UploadResponse = {
    status: number;
    message?: string;
    error?: string;
    error_type?: string;
    file?: {
        id: number;
        name: string;
        file_name: string;
        url: string;
        thumb: string;
        size: number;
        type: string;
        collection: string;
        alt_text: string;
        caption: string;
        delete_url: string;
        details_url: string;
    };
    variations?: Record<string, string>;
    conversion_status?: {
        status: string;
        conversions?: Record<string, boolean>;
    };
};

// =========================================================================
// Filter & page props
// =========================================================================

export type MediaFilters = {
    search: string;
    status: string;
    mime_type_category: string;
    created_by: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    view: 'table' | 'cards';
};

export type MediaStatistics = {
    total: number;
    trash: number;
};

export type MediaFilterOptions = {
    mime_type_category: { value: string; label: string }[];
    created_by: { value: string; label: string }[];
};

export type MediaIndexPageProps = {
    config: ScaffoldInertiaConfig;
    media: PaginatedData<MediaListItem>;
    filters: MediaFilters;
    statistics: MediaStatistics;
    uploadSettings: UploadSettings;
    storageData: StorageData;
    filterOptions: MediaFilterOptions;
    status?: string;
    error?: string;
};

// =========================================================================
// Media detail (from MediaController::getMediaDetails)
// =========================================================================

export type MediaDetail = {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    human_readable_size: string;
    created_at: string;
    updated_at: string | null;
    deleted_at: string | null;
    original_url: string;
    thumbnail_url: string;
    webp_url: string;
    media_url: string;
    variations: Record<string, string>;
    conversion_status: {
        status: string;
        conversions?: Record<string, boolean>;
        error?: string;
    };
    responsive_data: Record<string, unknown>;
    available_responsive_sizes: string[];
    width: number;
    height: number;
    is_small_image: boolean;
    owner: {
        id: number;
        name: string;
        email: string;
    } | null;
    alt_text: string;
    caption: string;
    tags: string;
    title: string;
    description: string;
    seo_title: string;
    seo_description: string;
    copyright: string;
    license: string;
    focal_point: { x: number; y: number } | null;
    is_processing: boolean;
    processing_failed: boolean;
    processing_error: string | null;
    uploaded_on: string;
};
