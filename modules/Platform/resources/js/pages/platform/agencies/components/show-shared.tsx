import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Badge } from '@/components/ui/badge';

export type StatusBadgeVariant =
    | 'success'
    | 'warning'
    | 'info'
    | 'danger'
    | 'secondary';

export type ConfirmState = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    tone: 'default' | 'destructive';
    action: () => void;
};

export type JsonRequestOptions = {
    method?: 'GET' | 'POST' | 'PATCH' | 'DELETE';
    body?: Record<string, unknown>;
    signal?: AbortSignal;
};

export type RelationActionOptions = {
    body?: Record<string, unknown>;
    reloadOnly?: Array<'agency' | 'servers' | 'dnsProviders' | 'cdnProviders' | 'activities'>;
    successMessage?: string;
    errorMessage?: string;
    onSuccess?: () => void;
};

type JsonResponse = {
    success?: boolean;
    message?: string;
};

export const INITIAL_CONFIRM: ConfirmState = {
    open: false,
    title: '',
    description: '',
    confirmLabel: 'Confirm',
    tone: 'default',
    action: () => {},
};

const STATUS_BADGE_VARIANT: Record<string, StatusBadgeVariant> = {
    active: 'success',
    inactive: 'secondary',
    trash: 'danger',
};

function csrfToken(): string | null {
    return document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? null;
}

export function statusBadgeVariant(status: string | null): StatusBadgeVariant {
    return STATUS_BADGE_VARIANT[status ?? ''] ?? 'secondary';
}

export function formatRelationStatus(value: string | null | undefined): string {
    if (!value) {
        return 'Unknown';
    }

    return value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

export function hostFromUrl(url: string | null): string | null {
    if (!url) {
        return null;
    }

    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}

export function InfoRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-3 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium text-foreground">
                {children}
            </span>
        </div>
    );
}

export function MetricBox({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="rounded-lg border bg-muted/30 p-3">
            <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-0.5 text-sm font-bold text-foreground">
                {value ?? '—'}
            </p>
        </div>
    );
}

export function HealthChip({
    label,
    tone,
}: {
    label: string;
    tone: 'success' | 'warning' | 'secondary';
}) {
    return (
        <Badge
            variant={
                tone === 'success'
                    ? 'success'
                    : tone === 'warning'
                      ? 'warning'
                      : 'secondary'
            }
        >
            {label}
        </Badge>
    );
}

export async function requestJson<T extends JsonResponse>(
    url: string,
    options: JsonRequestOptions = {},
): Promise<T> {
    const response = await fetch(url, {
        method: options.method ?? 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken() ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: options.body ? JSON.stringify(options.body) : undefined,
        signal: options.signal,
    });

    const payload = (await response.json()) as T;

    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || 'Operation failed. Please try again.');
    }

    return payload;
}

export function useAgencyRelationAction() {
    const [processing, setProcessing] = useState(false);

    async function perform(
        method: 'POST' | 'PATCH' | 'DELETE',
        url: string,
        options: RelationActionOptions = {},
    ): Promise<void> {
        setProcessing(true);

        try {
            const payload = await requestJson<JsonResponse>(url, {
                method,
                body: options.body,
            });

            showAppToast({
                variant: 'success',
                title:
                    payload.message ||
                    options.successMessage ||
                    'Operation completed successfully.',
            });

            options.onSuccess?.();

            router.reload({
                only: options.reloadOnly ?? [
                    'agency',
                    'servers',
                    'dnsProviders',
                    'cdnProviders',
                    'activities',
                ],
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title:
                    error instanceof Error
                        ? error.message
                        : options.errorMessage ||
                          'Operation failed. Please try again.',
            });
        } finally {
            setProcessing(false);
        }
    }

    return { processing, perform };
}
