import { router } from '@inertiajs/react';
import type {
    AppToastOptions,
    AppToastVariant,
} from '@/components/forms/form-success-toast';
import { showAppToast } from '@/components/forms/form-success-toast';
import type { FlashData, FlashMessage } from '@/types/auth';

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

    const entries: { key: AppToastVariant; message: FlashMessage }[] = [];

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
        const options = resolveFlashToastOptions(entry.key, entry.message);

        if (!options) {
            continue;
        }

        showAppToast(options);
    }
}

function resolveFlashToastOptions(
    variant: AppToastVariant,
    message: FlashMessage,
): AppToastOptions | null {
    if (typeof message === 'string') {
        const options: AppToastOptions = {
            variant,
            description: message,
        };

        if (message.length <= 40) {
            options.title = message;
            options.description = undefined;
        }

        return options;
    }

    if (!message || typeof message !== 'object') {
        return null;
    }

    const title = message.title?.trim();
    const description = message.message?.trim();

    if (!title && !description) {
        return null;
    }

    return {
        variant,
        title,
        description,
    };
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
