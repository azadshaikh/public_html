import type { MediaListItem, MediaPickerFilters, UploadSettings } from '@/types/media';
import type { PaginatedData } from '@/types/pagination';

export type ThemeCustomizerField = {
    label: string;
    type?: string;
    default?: string | number | boolean | null;
    helper_text?: string;
    description?: string;
    placeholder?: string;
    rows?: number;
    language?: string;
    options?: Record<string, string>;
};

export type ThemeCustomizerSection = {
    title: string;
    helper_text?: string;
    description?: string;
    settings?: Record<string, ThemeCustomizerField>;
};

export type ThemeCustomizerTheme = {
    name: string;
    directory: string | null;
    description?: string | null;
    screenshot?: string | null;
    author?: string | null;
    version?: string | null;
    is_child?: boolean;
    parent?: string | null;
};

export type ThemeCustomizerSnapshot = Record<string, string | number | boolean>;

export type DeviceMode = 'desktop' | 'tablet' | 'mobile';

export type CodeEditorState = {
    fieldId: string;
    label: string;
    language: string;
    value: string;
};

export type ThemeCustomizerPageProps = {
    activeTheme: ThemeCustomizerTheme;
    sections: Record<string, ThemeCustomizerSection>;
    initialValues: Record<string, string | number | boolean | null>;
    previewUrl: string;
    pickerMedia: PaginatedData<MediaListItem> | null;
    pickerFilters: MediaPickerFilters | null;
    uploadSettings: UploadSettings | null;
    pickerStatistics?: {
        total: number;
        trash: number;
    } | null;
};