import '../css/app.css';

import type { SharedData } from '@/types';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { toast } from 'vue-sonner';
import { route as ziggyRoute, ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const brandNames = [appName, 'Société de tir de Lavigny'];

const ziggyConfig: Omit<SharedData['ziggy'], 'location'> = {
    url: '',
    port: null,
    defaults: {},
    routes: {},
};

const route = ((name: string, params?: any, absolute?: boolean) => ziggyRoute(name, params, absolute, ziggyConfig)) as typeof ziggyRoute;

Object.assign(globalThis, { route });

createInertiaApp({
    title: (title) => {
        if (!title) {
            return appName;
        }

        return brandNames.some((brand) => title.includes(brand)) ? title : `${title} - ${appName}`;
    },
    serverHead: true,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        syncZiggyConfig((props.initialPage.props as unknown as SharedData).ziggy);

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, ziggyConfig)
            .mount(el);

        document.querySelector('script[data-page="app"][type="application/json"]')?.remove();
        return app;
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

router.on('success', (event) => {
    syncZiggyConfig((event.detail.page.props as unknown as SharedData).ziggy);

    const flash = (event.detail.page.props as Record<string, any>).flash;
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.warning) toast.warning(flash.warning);
});

router.on('beforeUpdate', (event) => {
    syncZiggyConfig((event.detail.page.props as unknown as SharedData).ziggy);
});

function syncZiggyConfig(config: SharedData['ziggy'] | undefined): void {
    if (!config) {
        return;
    }

    ziggyConfig.url = config.url;
    ziggyConfig.port = config.port;
    ziggyConfig.defaults = config.defaults;
    ziggyConfig.routes = config.routes;
}
