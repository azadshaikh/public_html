import type { Key } from 'react';

export function normalizeRowKey(key: Key): string {
    return String(key);
}

export function collectFormParams(
    form: HTMLFormElement,
): Record<string, string> {
    return Object.fromEntries(
        Array.from(new FormData(form).entries()).map(([key, value]) => [
            key,
            String(value),
        ]),
    );
}

export function cleanParams(
    params: Record<string, string | number | null | undefined>,
): Record<string, string | number> {
    return Object.fromEntries(
        Object.entries(params).filter(
            ([, value]) =>
                value !== '' && value !== null && value !== undefined,
        ),
    ) as Record<string, string | number>;
}

export function normalizePaginationLabel(label: string): string {
    return label
        .replaceAll('&laquo;', '')
        .replaceAll('&raquo;', '')
        .replaceAll('&hellip;', '...')
        .replace(/<[^>]+>/g, '')
        .trim();
}
