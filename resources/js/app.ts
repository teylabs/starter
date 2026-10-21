import { createInertiaApp, router } from '@inertiajs/vue3';
import { putConfig, withInertiaModal } from '@inertiaui/modal-vue';
import { createApp, h, type DefineComponent } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@kit/ui';
import { toast } from 'vue-sonner';
import { honourReducedMotion } from '@/lib/reduced-motion';
import ShellLayout from '@/layouts/ShellLayout.vue';


// Pages live at ./pages/<Resource>/<Section>/Page.vue and declare no layout: the shell is the server's pick (`shell.kind`: guest, focus or app, fed by the
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

        // The server names the shell per request (`shell.kind`): app, guest or focus.
        return sublayout ? [ShellLayout, sublayout] : ShellLayout;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        // Server-driven modals (inertiaui/modal): the package mounts them itself, so no slot is needed in a layout.
        withInertiaModal(app as never);
        app
            .use(plugin)
            .directive('focus', { mounted: (element: HTMLElement, binding) => { if (binding.value !== false) element.focus(); } })
            .mount(el);
    },
});

initializeTheme();
// A `toast` the server flashes with a redirect (a cleared bad sort, say) is shown as one.
router.on('flash', (event) => {
    const message = (event.detail.flash as { toast?: { message?: string } } | undefined)?.toast?.message;
    if (message) toast(message);
});
// Not the browser's native <dialog>: it sits in the top layer, above every <Dialog> that a modal opens over itself.
putConfig({ useNativeDialog: false });
honourReducedMotion();
