import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { toast } from 'vue-sonner';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
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
    const flash = (event.detail.page.props as Record<string, any>).flash;
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

router.on('navigate', (event) => {
    requestAnimationFrame(() => {
        if (window._paq) {
            const url = event.detail.page.url;
            window._paq.push(['setCustomUrl', url]);
            window._paq.push(['setDocumentTitle', document.title]);
            window._paq.push(['trackPageView']);
        }
    });
});
