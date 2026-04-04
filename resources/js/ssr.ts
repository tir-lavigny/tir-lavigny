import type { SharedData } from '@/types';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import { route as ziggyRoute } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) });

            // Configure Ziggy for SSR...
            const sharedProps = page.props as unknown as SharedData;
            const ziggyConfig = {
                ...sharedProps.ziggy,
                location: new URL(sharedProps.ziggy.location),
            };

            // Create route function wrapping ziggy with SSR config...
            const route = ((...args: Parameters<typeof ziggyRoute>) =>
                (ziggyRoute as (...args: any[]) => any)(...args.slice(0, 3), ziggyConfig)) as typeof ziggyRoute;

            // Make route function available globally...
            app.config.globalProperties.route = route;

            // Make route function available globally for SSR...
            if (typeof window === 'undefined') {
                (globalThis as any).route = route;
            }

            app.use(plugin);

            return app;
        },
    }),
);
