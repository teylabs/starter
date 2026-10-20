import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@kit/ui';
import AppLayout from '@/layouts/AppLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';


// Pages live at ./pages/<Resource>/<Section>/Page.vue and declare no layout: sign-in pages get the guest shell, everything else the app shell (fed by the
// backend's shared `shell`), and a page inside a folder that has a Layout.vue (pages/Workspace/Settings, pages/Workspace/Account) is wrapped in that one too,
// the nearest one up its own resource folder. Those sublayouts are imported eagerly so moving between siblings keeps the same instance mounted. A Layout.vue at
// the pages root or directly in `Workspace` (the kit's own shell component, not a layout to use) is never picked.
const pages = import.meta.glob<DefineComponent>('./pages/**/Page.vue');
const sublayouts = import.meta.glob<DefineComponent>('./pages/*/*/**/Layout.vue', { eager: true, import: 'default' });

function nearestPageLayout(name: string): DefineComponent | null {
    const folders = name.split('/').slice(0, -1);
    for (let depth = folders.length; depth >= 2; depth--) {
        const layout = sublayouts[`./pages/${folders.slice(0, depth).join('/')}/Layout.vue`];
        if (layout) return layout;
    }

    return null;
}

createInertiaApp({
    resolve: async (name) => {
        const page = pages[`./pages/${name}.vue`];
        if (!page) throw new Error(`Unknown page: ${name}`);
        return page();
    },
    layout: (name) => {
        const sublayout = nearestPageLayout(name);

        const shell = name.startsWith('Auth/') ? GuestLayout : AppLayout;

        return sublayout ? [shell, sublayout] : shell;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('focus', { mounted: (element: HTMLElement, binding) => { if (binding.value !== false) element.focus(); } })
            .mount(el);
    },
});

initializeTheme();
