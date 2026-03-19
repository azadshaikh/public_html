import { router, useHttp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Badge } from '@/components/ui/badge';

export type StatusBadgeVariant = 'success' | 'warning' | 'info' | 'danger' | 'secondary';

export const STATUS_BADGE_VARIANT: Record<string, StatusBadgeVariant> = {
    active: 'success',
    provisioning: 'info',
    ready: 'success',
    failed: 'danger',
    maintenance: 'warning',
    inactive: 'secondary',
    trash: 'danger',
    deleted: 'danger',
};

export const STEP_STATUS_VARIANT: Record<string, StatusBadgeVariant> = {
    done: 'success',
    provisioning: 'info',
    failed: 'danger',
    pending: 'warning',
};

export function statusBadgeVariant(status: string | null): StatusBadgeVariant {
    return STATUS_BADGE_VARIANT[status ?? ''] ?? 'secondary';
}

export function formatStatusLabel(status: string | null): string {
    if (!status) {
        return 'Unknown';
    }

    return status.replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}

export function HealthChip({ label, status }: { label: string; status: string | null }) {
    return (
        <Badge variant={statusBadgeVariant(status)}>
            {label ? `${label}: ` : ''}
            {formatStatusLabel(status)}
        </Badge>
    );
}

export function InfoRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-2 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium text-foreground">{children}</span>
        </div>
    );
}

export type ConfirmState = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    tone: 'default' | 'destructive';
    action: () => void;
};

export const INITIAL_CONFIRM: ConfirmState = {
    open: false,
    title: '',
    description: '',
    confirmLabel: 'Confirm',
    tone: 'default',
    action: () => {},
};

export function useOperationAction() {
    const request = useHttp<Record<string, never>, { status?: string; message?: string }>({});

    function perform(
        method: 'post' | 'delete' | 'patch',
        url: string,
        {
            onSuccess,
            onError,
        }: {
            onSuccess?: (message: string) => void;
            onError?: (message: string) => void;
        } = {},
    ) {
        const options = {
            headers: { Accept: 'application/json' } as Record<string, string>,
            preserveScroll: true,
            onSuccess: () => {
                const message = 'Operation completed successfully.';

                if (onSuccess) {
                    onSuccess(message);

                    return;
                }

                showAppToast({ variant: 'success', title: message });
                router.reload();
            },
            onError: () => {
                const message = 'Operation failed. Please try again.';

                if (onError) {
                    onError(message);

                    return;
                }

                showAppToast({ variant: 'error', title: message });
            },
        };

        if (method === 'post') {
            void request.post(url, options);

            return;
        }

        if (method === 'delete') {
            void request.delete(url, options);

            return;
        }

        void request.patch(url, options);
    }

    return { processing: request.processing, perform };
}