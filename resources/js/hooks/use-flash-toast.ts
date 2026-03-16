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
const _recentFlashToastTimestamps = new Map<string, number>();

function cleanupRecentFlashToastTimestamps(now: number): void {
    for (const [signature, timestamp] of _recentFlashToastTimestamps) {
        if (now - timestamp > 1500) {
            _recentFlashToastTimestamps.delete(signature);
        }
    }
}

function buildFlashToastSignature(options: AppToastOptions): string {
    return JSON.stringify({
        variant: options.variant ?? 'success',
        title: options.title ?? '',
        description: options.description ?? '',
    });
}

function shouldSkipFlashToast(options: AppToastOptions): boolean {
    const now = Date.now();
    const signature = buildFlashToastSignature(options);
    const previousTimestamp = _recentFlashToastTimestamps.get(signature);

    cleanupRecentFlashToastTimestamps(now);

    if (previousTimestamp !== undefined && now - previousTimestamp <= 1500) {
        return true;
    }

    _recentFlashToastTimestamps.set(signature, now);

    return false;
}

export function suppressNextFlashToast(): void {
    _suppressCount++;
}

export function releaseSuppressedFlashToast(): void {
    if (_suppressCount > 0) {
        _suppressCount--;
    }
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

        if (shouldSkipFlashToast(options)) {
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
        return {
            id: `flash:${variant}:${message}`,
            variant,
            description: message,
        };
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
        id: `flash:${variant}:${title ?? ''}:${description ?? ''}`,
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
    router.on('success', (event) => {
        const flash = event.detail?.page?.props?.flash as FlashData | undefined;

        if (flash) {
            showFlashToasts(flash);
        }
    });
}
