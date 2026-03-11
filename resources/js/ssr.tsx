import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { TooltipProvider } from '@/components/ui/tooltip';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        pages: {
            path: './pages',
            extension: '.tsx',
        },
        defaults: {
            form: {
                recentlySuccessfulDuration: 5000,
            },
            prefetch: {
                cacheFor: '1m',
                hoverDelay: 150,
            },
        },
        setup: ({ App, props }) => (
            <TooltipProvider>
                <App {...props} />
            </TooltipProvider>
        ),
    }),
);
