import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import path from 'path';
import removeAttr from 'remove-attr';
import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        sourcemap: (process.env.APP_ENV || 'local') === 'local',
    },
    server: {
        // Requis pour le HMR depuis un navigateur hôte quand Vite tourne dans Docker
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: process.env.VITE_HMR_HOST ?? 'localhost',
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        ...[
            (process.env.APP_ENV || 'local') === 'production'
                ? removeAttr({
                      extensions: ['vue'],
                      attributes: ['data-testid'],
                  })
                : [],
        ],
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
            'ziggy-js': resolve(import.meta.dirname, 'vendor/tightenco/ziggy'),
        },
    },
});
