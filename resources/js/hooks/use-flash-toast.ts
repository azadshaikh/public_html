import { router } from '@inertiajs/react';
import type {
    AppToastOptions,
    AppToastVariant,
} from '@/components/forms/form-success-toast';
import { showAppToast } from '@/components/forms/form-success-toast';
import type { FlashData } from '@/types/auth';

/**
 * Module-level flag to suppress the next flash toast.
 *
 * Used by `useAppForm` to prevent duplicate toasts when
 * the form's own `successToast` option is active and the
 * backend also sends a flash message on the same request.
 */
let _suppressCount = 0;

export function suppressNextFlashToast(): void {
    _suppressCount++;
}

function showFlashToasts(flash: FlashData): void {
    if (!flash || typeof flash !== 'object') {
        return;
    }

    const entries: { key: AppToastVariant; message: string }[] = [];

    if (flash.success) {
        entries.push({ key: 'success', message: flash.success });
    }
    if (flash.error) {
        entries.push({ key: 'error', message: flash.error });
    }
    if (flash.info) {
        entries.push({ key: 'info', message: flash.info });
    }
    if (flash.status) {
        entries.push({ key: 'success', message: flash.status });
    }

    if (entries.length === 0) {
        return;
    }

    // If useAppForm already showed a toast for this request, skip.
    if (_suppressCount > 0) {
        _suppressCount--;
        return;
    }

    for (const entry of entries) {
        const options: AppToastOptions = {
            variant: entry.key,
            description: entry.message,
        };

        // Use description as title for short messages, keep default title for longer ones.
        if (entry.message.length <= 40) {
            options.title = entry.message;
            options.description = undefined;
        }

        showAppToast(options);
    }
}

/**
 * Registers a global Inertia router listener that shows Sonner
 * toasts for flash messages (success / error / info / status).
 *
 * Call once at app bootstrap — no React context required.
 */
export function initFlashToasts(): void {
    router.on('navigate', (event) => {
        const flash = event.detail?.page?.props?.flash as
            | FlashData
            | undefined;

        if (flash) {
            showFlashToasts(flash);
        }
    });
}
