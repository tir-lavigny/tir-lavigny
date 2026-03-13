import vue from '@vitejs/plugin-vue';
import path from 'path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: ['./resources/js/tests/setup.ts'],
        include: ['resources/js/**/*.test.ts'],
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
        coverage: {
            include: ['resources/js/**'],
            exclude: ['resources/js/**/*.test.ts', 'resources/js/tests/**'],
            provider: 'v8',
        },
    },
});
