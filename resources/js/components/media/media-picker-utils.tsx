import {
    FileAudioIcon,
    FileIcon,
    FileSpreadsheetIcon,
    FileTextIcon,
    FileVideoIcon,
} from 'lucide-react';
import type { MediaListItem } from '@/types/media';

export function formatSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

export function isImageMime(mime: string): boolean {
    return mime.startsWith('image/');
}

export function getFileExtension(fileName: string): string {
    const ext = fileName.split('.').pop();
    return ext ? ext.toUpperCase() : '?';
}

export function getFileTypeIcon(mimeType: string, className: string) {
    if (mimeType.startsWith('video/')) {
        return <FileVideoIcon className={className} />;
    }
    if (mimeType.startsWith('audio/')) {
        return <FileAudioIcon className={className} />;
    }
    if (
        mimeType === 'application/pdf' ||
        mimeType.includes('word') ||
        mimeType.includes('document') ||
        mimeType === 'text/plain'
    ) {
        return <FileTextIcon className={className} />;
    }
    if (
        mimeType.includes('spreadsheet') ||
        mimeType.includes('excel') ||
        mimeType === 'text/csv'
    ) {
        return <FileSpreadsheetIcon className={className} />;
    }
    return <FileIcon className={className} />;
}

export type MediaPickerItem = {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    human_readable_size: string;
    thumbnail_url: string | null;
    original_url: string | null;
    media_url: string | null;
    alt_text: string;
    created_at: string;
};

export type MediaPickerSelection = 'single' | 'multiple';

export function toPickerItem(item: MediaListItem): MediaPickerItem {
    return {
        id: item.id,
        name: item.name,
        file_name: item.file_name,
        mime_type: item.mime_type,
        size: item.size,
        human_readable_size: item.human_readable_size,
        thumbnail_url: item.thumbnail_url,
        original_url: item.original_url,
        media_url: item.media_url,
        alt_text: item.alt_text,
        created_at: item.created_at,
    };
}
