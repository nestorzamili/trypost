import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import i18n from 'laravel-vue-i18n/vite';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        origin: 'http://localhost:5173',
        // Docker bind mounts on Windows/macOS do not propagate inotify
        // events, so the watcher never fires and HMR silently stops. Polling
        // fixes it at a small CPU cost — enabled via VITE_WATCH_POLLING in
        // compose.yaml (dev only; production builds are unaffected).
        watch:
            process.env.VITE_WATCH_POLLING === '1'
                ? { usePolling: true, interval: 500 }
                : undefined,
        cors: {
            origin: 'http://localhost:8000',
        },
        hmr: {
            host: 'localhost',
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts', 'resources/css/app.css'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        i18n(),
    ],
});
