import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { resolveInertiaPage } from './lib/inertia-page-resolver';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const inertiaDefaults = {
    form: {
        recentlySuccessfulDuration: 5000,
    },
    future: {
        useDataInertiaHeadAttribute: true,
    },
    prefetch: {
        cacheFor: '1m',
        hoverDelay: 150,
    },
};

createInertiaApp({
    title: (title) => (title ? `${title} | ${appName}` : appName),
    resolve: resolveInertiaPage,
    defaults: inertiaDefaults,
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        const app = (
            <StrictMode>
                <TooltipProvider>
                    <App {...props} />
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
