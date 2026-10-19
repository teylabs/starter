import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent, type VNode } from 'vue';
import '../css/app.css';

// tey/kit pages live at ./pages/<Resource>/<Action>/Page.vue. A page declares no layout: the nearest folder
// Layout.vue within its resource wraps it, with the backend's shared `layout` props.
const pages = import.meta.glob<DefineComponent>('./pages/**/Page.vue');
const layouts = import.meta.glob<DefineComponent>('./pages/**/Layout.vue');

function nearestLayout(name: string): string | undefined {
    const folders = name.split('/').slice(0, -1);
    for (let depth = folders.length - 1; depth >= 1; depth--) {
        const path = `./pages/${folders.slice(0, depth).join('/')}/Layout.vue`;
        if (path in layouts) return path;
    }
    return undefined;
}

createInertiaApp({
    resolve: async (name) => {
        const page = pages[`./pages/${name}.vue`];
        if (!page) throw new Error(`Unknown page: ${name}`);
        const component = await page();
        const layout = nearestLayout(name);
        if (layout) {
            const Layout = (await layouts[layout]()).default;
            component.default.layout = (render: typeof h, child: VNode & { props: { layout: object } }) => render(Layout, child.props.layout, () => child);
        }
        return component;
    },
    setup({ el, App, props, plugin }) { createApp({ render: () => h(App, props) }).use(plugin).mount(el); },
});
