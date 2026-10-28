import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { execSync } from 'node:child_process';
import { resolve } from 'node:path';
import path from 'path';
import removeAttr from 'remove-attr';
import { defineConfig } from 'vite';

// CI passes the release/dev/rev tag as VITE_BUILD_TAG; local builds fall back
// to the current commit short sha so the footer always shows what it is.
process.env.VITE_BUILD_TAG ??= (() => {
    try {
        return execSync('git rev-parse --short HEAD').toString().trim();
    } catch {
        return 'unknown';
    }
})();

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
