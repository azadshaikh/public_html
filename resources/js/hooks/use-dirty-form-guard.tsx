import type { PendingVisit, VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { AlertTriangleIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';

type UseDirtyFormGuardOptions = {
    enabled: boolean;
    message?: string;
    onDiscard?: () => void;
    shouldPrompt?: () => boolean;
    shouldPromptForVisit?: (visit: PendingVisit) => boolean;
};

type UseDirtyFormGuardResult = {
    dialog: ReactNode;
};

const DEFAULT_MESSAGE =
    'You have unsaved changes. Are you sure you want to leave this page?';

function resumeVisit(visit: PendingVisit): void {
    const { url, completed, cancelled, interrupted, ...options } = visit;

    void completed;
    void cancelled;
    void interrupted;

    router.visit(url, options as VisitOptions);
}

export function useDirtyFormGuard({
    enabled,
    message = DEFAULT_MESSAGE,
    onDiscard,
    shouldPrompt = () => true,
    shouldPromptForVisit = (visit) => {
        if (visit.preserveState === true) {
            return false;
        }
        return visit.method === 'get';
    },
}: UseDirtyFormGuardOptions): UseDirtyFormGuardResult {
    const [pendingVisit, setPendingVisit] = useState<PendingVisit | null>(null);
    const [open, setOpen] = useState(false);
    const bypassPromptRef = useRef(false);

    useEffect(() => {
        if (!enabled || typeof window === 'undefined') {
            return;
        }

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!shouldPrompt() || bypassPromptRef.current) {
                return undefined;
            }

            event.preventDefault();
            event.returnValue = message;

            return message;
        };

        const removeInertiaListener = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                bypassPromptRef.current ||
                !shouldPromptForVisit(visit) ||
                !shouldPrompt()
            ) {
                return true;
            }

            setPendingVisit(visit);
            setOpen(true);

            return false;
        });

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            removeInertiaListener();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [enabled, message, shouldPrompt, shouldPromptForVisit]);

    const handleCancel = useCallback(() => {
        setOpen(false);
        setPendingVisit(null);
    }, []);

    const handleConfirm = useCallback(() => {
        if (pendingVisit === null) {
            setOpen(false);

            return;
        }

        bypassPromptRef.current = true;
        setOpen(false);
        const visitToResume = pendingVisit;
        setPendingVisit(null);
        onDiscard?.();
        resumeVisit(visitToResume);

        requestAnimationFrame(() => {
            bypassPromptRef.current = false;
        });
    }, [onDiscard, pendingVisit]);

    const dialog = useMemo(
        () => (
            <ConfirmationDialog
                open={open}
                onOpenChange={setOpen}
                title="Discard unsaved changes?"
                description={message}
                cancelLabel="Stay on page"
                confirmLabel="Leave page"
                icon={<AlertTriangleIcon className="size-4.5" />}
                onConfirm={handleConfirm}
                onCancel={handleCancel}
                cancelClassName="hover:bg-muted/60"
                confirmClassName="bg-destructive text-white hover:bg-destructive/90"
            />
        ),
        [handleCancel, handleConfirm, message, open],
    );

    return {
        dialog,
    };
}
