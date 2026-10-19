import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

// Pages live at ./pages/<Resource>/<Action>/Page.vue and declare no layout: sign-in pages get the guest shell,
// everything else the app shell, fed by the backend's shared `layout` props. tey/kit's own folder Layout.vue
// (pages/Workspace/Layout.vue) is its unstyled starting point and is not used.
const pages = import.meta.glob<DefineComponent>('./pages/**/Page.vue');

createInertiaApp({
    resolve: async (name) => {
        const page = pages[`./pages/${name}.vue`];
        if (!page) throw new Error(`Unknown page: ${name}`);
        return page();
    },
    layout: (name) => (name.startsWith('Auth/') ? GuestLayout : AppLayout),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('focus', { mounted: (element: HTMLElement, binding) => { if (binding.value !== false) element.focus(); } })
            .mount(el);
    },
});

initializeTheme();
