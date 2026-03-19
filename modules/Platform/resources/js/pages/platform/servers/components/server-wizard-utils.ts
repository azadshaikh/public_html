export type WizardStep = 'mode' | 'manual' | 'provision';

export type ConnectionState = {
    status: 'idle' | 'loading' | 'success' | 'error';
    message: string;
    osInfo?: string | null;
};

export const INITIAL_CONNECTION_STATE: ConnectionState = {
    status: 'idle',
    message: 'Not connected',
};

export type JsonErrorPayload = {
    success?: boolean;
    message?: string;
    errors?: Record<string, string[] | string>;
};

export function csrfToken(): string | undefined {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? undefined
    );
}

export function isWizardMode(
    value: string,
): value is Extract<WizardStep, 'manual' | 'provision'> {
    return value === 'manual' || value === 'provision';
}

export function extractErrorMessage(
    payload: JsonErrorPayload | null,
    fallback: string,
): string {
    if (payload?.message && payload.message.trim() !== '') {
        return payload.message;
    }

    if (payload?.errors) {
        const firstError = Object.values(payload.errors)[0];

        if (Array.isArray(firstError)) {
            return firstError[0] ?? fallback;
        }

        if (typeof firstError === 'string' && firstError.trim() !== '') {
            return firstError;
        }
    }

    return fallback;
}

export async function parseJsonResponse<T>(
    response: Response,
    fallbackMessage = 'Request failed.',
): Promise<T> {
    const responseText = await response.text();
    let payload: (T & JsonErrorPayload) | null = null;

    if (responseText.trim() !== '') {
        try {
            payload = JSON.parse(responseText) as T & JsonErrorPayload;
        } catch {
            throw new Error(fallbackMessage);
        }
    }

    if (!response.ok) {
        throw new Error(extractErrorMessage(payload, fallbackMessage));
    }

    if (payload && 'success' in payload && payload.success === false) {
        throw new Error(extractErrorMessage(payload, fallbackMessage));
    }

    return (payload ?? {}) as T;
}
