import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { kitImports } from './resources/js/components/Kit/kitImports.js';

// tey/kit pages type their props with the generated global ViewModel types; the SFC compiler resolves them from here.
const globalTypeFiles = [decodeURIComponent(new URL('./resources/js/types.generated.d.ts', import.meta.url).pathname)];

// Kit pages and the shell import UI only through `@kit/ui`. It resolves to the kit's own set; point it at your own module to use your own components (see the kit README).
const kitUi = decodeURIComponent(new URL('./resources/js/components/Kit/index.ts', import.meta.url).pathname);
// The shell Layout takes UserMenu and WorkspaceSwitcher only through `@kit/shell`, the generated Partials barrel; point it at your own module to replace them (or use the Layout's slots).
const kitShell = decodeURIComponent(new URL('./resources/js/pages/Workspace/Partials/index.ts', import.meta.url).pathname);

// Production chunking: one long-cacheable chunk per node_modules package (`@scope/name` for scoped ones), so upgrading one
// dependency invalidates only its own chunk and no vendor chunk grows past Vite's 500 kB warning. Vite 8 bundles with Rolldown,
// so this is Rolldown's `codeSplitting.groups` (the successor of Rollup's `manualChunks`).
function packageChunk(id: string): string | null {
    const path = id.split('node_modules/').pop();

    if (path === undefined || path === id) {
        return null;
    }

    const [scope, name] = path.split('/');

    return `vendor-${(scope.startsWith('@') ? `${scope.slice(1)}-${name}` : scope).replace(/[^\w.-]/g, '_')}`;
}

// Dev server: every browser dependency is pre-bundled up front, so a page that first imports one (the editor's tiptap, the table's
// tanstack) never makes Vite re-optimize and reload mid-session; @lucide/vue becomes one module instead of one request per icon.
// The app's own .vue files (the `@kit/ui` barrel, its components, the layouts) cannot be pre-bundled; warmup transforms them as the
// server starts, so the first page load is served from the transform cache.
const preBundled = [
    '@inertiajs/vue3', '@inertiaui/modal-vue', '@internationalized/date', '@lucide/vue', '@tanstack/vue-table', '@tiptap/core',
    '@tiptap/extension-code', '@tiptap/extension-placeholder', '@tiptap/extension-text-align', '@tiptap/extension-text-style',
    '@tiptap/pm/history', '@tiptap/starter-kit', '@tiptap/vue-3', '@vueuse/core', 'class-variance-authority', 'clsx', 'reka-ui',
    'tailwind-merge', 'vue', 'vue-draggable-plus', 'vue-sonner',
];
const warmup = ['./resources/js/app.ts', './resources/js/components/Kit/index.ts', './resources/js/layouts/*.vue', './resources/js/pages/Workspace/Partials/index.ts'];

// Dev only: kitImports rewrites each `@kit/ui` import into imports of the files it names, so a page requests only the kit components it
// uses instead of every file the barrel lists (tey/kit docs/kit-imports.md). Builds are unchanged: the bundler already tree-shakes the barrel.
export default defineConfig({
    build: {
        rolldownOptions: {
            // Each package's chunk holds only that package: its dependencies stay in their own chunks. Rolldown asks for strict
            // execution order when groups don't pull in their dependencies, so module side effects still run in import order.
            output: { strictExecutionOrder: true, codeSplitting: { includeDependenciesRecursively: false, groups: [{ name: packageChunk, test: /node_modules/ }] } },
        },
    },
    optimizeDeps: { include: preBundled },
    server: { warmup: { clientFiles: warmup } },
    resolve: { alias: { '@kit/ui': kitUi, '@kit/shell': kitShell } },
    plugins: [tailwindcss(), laravel({ input: ['resources/js/app.ts'], refresh: true }), vue({ script: { globalTypeFiles } }), kitImports()],
});
