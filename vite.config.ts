import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import basicSsl from '@vitejs/plugin-basic-ssl';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = new URL(env.APP_URL || 'http://localhost');
    const devServerHost = env.VITE_DEV_SERVER_HOST || appUrl.hostname;
    const devServerPort = Number(env.VITE_DEV_SERVER_PORT || 5173);
    const useHttps = env.VITE_DEV_SERVER_HTTPS === 'true';
    const devServerProtocol = useHttps ? 'https' : 'http';
    const appOrigin = appUrl.origin;

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
            }),
            inertia(),
            ...(useHttps ? [basicSsl()] : []),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            wayfinder({
                formVariants: true,
            }),
        ],
        server: {
            host: '0.0.0.0',
            port: devServerPort,
            strictPort: true,
            watch: {
                ignored: ['**/tmp/**'],
            },
            cors: {
                origin: [appOrigin],
            },
            origin: `${devServerProtocol}://${devServerHost}:${devServerPort}`,
            hmr: {
                host: devServerHost,
                port: devServerPort,
                protocol: useHttps ? 'wss' : 'ws',
            },
        },
        esbuild: {
            jsx: 'automatic',
        },
    };
});
