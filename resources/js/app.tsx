import { createInertiaApp, router } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import AppConnectionStatus from '@/components/app-connection-status';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initFlashToasts } from '@/hooks/use-flash-toast';
import {
    initModulePageFilter,
    resolveInertiaPage,
} from '@/lib/inertia-page-resolver';
import {
    incrementInertiaNavigationCount,
    normalizeInertiaNavigationUrl,
    resetInertiaNavigationCount,
    shouldForceInertiaHardReload,
    shouldTrackInertiaNavigation,
} from './lib/inertia-session-reload';
import { pushRecentQuickOpenUrl } from './lib/quick-open.js';

const inertiaDefaults = {
    form: {
        recentlySuccessfulDuration: 5000,
    },
    prefetch: {
        cacheFor: '1m',
        hoverDelay: 150,
    },
};

function syncAdminTheme(sharedProps: Record<string, unknown>): void {
    const adminTheme = (sharedProps.ui as { adminTheme?: string } | undefined)
        ?.adminTheme;

    if (typeof adminTheme !== 'string' || adminTheme.length === 0) {
        document.documentElement.removeAttribute('data-admin-theme');

        return;
    }

    document.documentElement.dataset.adminTheme = adminTheme;
}

function syncModulePageFilter(sharedProps: Record<string, unknown>): void {
    initModulePageFilter(
        sharedProps.modules as
            | {
                  items: Array<{
                      name?: string;
                      slug?: string;
                      inertiaNamespace?: string;
                  }>;
              }
            | undefined,
    );
}

function resolveInertiaHardReloadPageLimit(
    sharedProps: Record<string, unknown>,
): number {
    const limit = (
        sharedProps.runtime as
            | { inertiaHardReloadPageLimit?: number }
            | undefined
    )?.inertiaHardReloadPageLimit;

    return typeof limit === 'number' && Number.isFinite(limit) ? limit : 15;
}

createInertiaApp({
    resolve: resolveInertiaPage,
    defaults: inertiaDefaults,
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        // Initialise the module page filter from shared props so disabled
        // module pages are excluded from the page registry before the very
        // first Inertia page resolution.
        const sharedProps = props.initialPage.props as Record<string, unknown>;
        syncAdminTheme(sharedProps);
        syncModulePageFilter(sharedProps);
        let inertiaHardReloadPageLimit =
            resolveInertiaHardReloadPageLimit(sharedProps);

        let lastTrackedPageUrl =
            normalizeInertiaNavigationUrl(props.initialPage.url) ?? '/';
        let latestStartedVisit: {
            method?: string;
            prefetch?: boolean;
            only?: string[];
            except?: string[];
        } | null = null;

        pushRecentQuickOpenUrl(window.localStorage, props.initialPage.url);

        router.on('start', (event) => {
            latestStartedVisit = event.detail.visit;
        });

        router.on('success', (event) => {
            const nextSharedProps = event.detail?.page?.props as
                | Record<string, unknown>
                | undefined;

            if (nextSharedProps) {
                syncAdminTheme(nextSharedProps);
                syncModulePageFilter(nextSharedProps);
                inertiaHardReloadPageLimit =
                    resolveInertiaHardReloadPageLimit(nextSharedProps);
            }

            const nextPageUrl = event.detail?.page?.url ?? null;

            if (
                !shouldTrackInertiaNavigation(
                    latestStartedVisit,
                    nextPageUrl,
                    lastTrackedPageUrl,
                )
            ) {
                latestStartedVisit = null;

                return;
            }

            lastTrackedPageUrl =
                normalizeInertiaNavigationUrl(nextPageUrl) ??
                lastTrackedPageUrl;
            latestStartedVisit = null;
            pushRecentQuickOpenUrl(window.localStorage, lastTrackedPageUrl);

            const navigationCount = incrementInertiaNavigationCount(
                window.sessionStorage,
            );

            if (
                !shouldForceInertiaHardReload(
                    navigationCount,
                    inertiaHardReloadPageLimit,
                )
            ) {
                return;
            }

            resetInertiaNavigationCount(window.sessionStorage);
            window.location.reload();
        });

        const app = (
            <StrictMode>
                <TooltipProvider>
                    <App {...props} />
                    <AppConnectionStatus />
                    <Toaster
                        position="top-right"
                        closeButton={false}
                        expand={false}
                        richColors={false}
                    />
                </TooltipProvider>
            </StrictMode>
        );

        createRoot(el).render(app);
    },
    progress: {
        color: '#4B5563',
        delay: 0,
        showSpinner: false,
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Listen for server-side flash messages and show Sonner toasts.
initFlashToasts();
