import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import '../css/app.css';

// Each starter feature keeps its pages under ./starter/<feature>/pages; page names stay feature-independent.
const pages = import.meta.glob<DefineComponent>('./starter/*/pages/**/*.vue');
createInertiaApp({
    resolve: async (name) => {
        const matches = Object.keys(pages).filter((path) => path.split('/pages/')[1] === `${name}.vue`);
        const page = matches.length === 1 ? pages[matches[0]] : undefined;
        if (!page) throw new Error(`Unknown page: ${name}`);
        return page();
    },
    setup({ el, App, props, plugin }) { createApp({ render: () => h(App, props) }).use(plugin).mount(el); },
});
