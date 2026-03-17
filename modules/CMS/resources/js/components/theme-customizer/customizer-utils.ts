import type {
    ThemeCustomizerField,
    ThemeCustomizerSnapshot,
} from '../../pages/cms/themes/customizer/types';

export function normalizeFieldValue(
    value: string | number | boolean | null | undefined,
): string | number | boolean {
    if (typeof value === 'boolean' || typeof value === 'number') {
        return value;
    }

    return value ?? '';
}

export function buildPreviewUrl(url: string, cacheBuster?: string): string {
    const resolved = new URL(url, window.location.origin);
    resolved.searchParams.set('customizer_preview', '1');

    if (cacheBuster) {
        resolved.searchParams.set('_preview', cacheBuster);
    }

    return resolved.toString();
}

export function decodeCurrentPreviewLocation(currentUrl: string): string {
    const resolved = new URL(currentUrl, window.location.origin);
    resolved.searchParams.delete('_preview');

    return resolved.toString();
}

export function toFormData(values: ThemeCustomizerSnapshot): FormData {
    const formData = new FormData();

    Object.entries(values).forEach(([key, value]) => {
        if (typeof value === 'boolean') {
            formData.set(key, value ? 'true' : 'false');

            return;
        }

        formData.set(key, String(value ?? ''));
    });

    return formData;
}

export function getFieldDescription(
    field: ThemeCustomizerField,
): string | undefined {
    return field.helper_text ?? field.description;
}