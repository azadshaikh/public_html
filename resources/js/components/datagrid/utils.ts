import type { Key } from 'react';

export function normalizeRowKey(key: Key): string {
    return String(key);
}

export function collectFormParams(
    form: HTMLFormElement,
): Record<string, string> {
    const params: Record<string, string> = {};

    for (const [key, value] of new FormData(form).entries()) {
        const normalizedValue = String(value);

        if (!(key in params)) {
            params[key] = normalizedValue;

            continue;
        }

        params[key] = [params[key], normalizedValue]
            .filter((entry) => entry !== '')
            .join(',');
    }

    return params;
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
