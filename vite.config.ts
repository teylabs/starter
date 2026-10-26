import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// tey/kit pages type their props with the generated global ViewModel types; the SFC compiler resolves them from here.
const globalTypeFiles = [decodeURIComponent(new URL('./resources/js/types.generated.d.ts', import.meta.url).pathname)];

// Kit pages and the shell import UI only through `@kit/ui`. It resolves to the kit's own set; point it at your own module to use your own components (see the kit README).
const kitUi = decodeURIComponent(new URL('./resources/js/components/Kit/index.ts', import.meta.url).pathname);
// The shell Layout takes UserMenu and WorkspaceSwitcher only through `@kit/shell`, the generated Partials barrel; point it at your own module to replace them (or use the Layout's slots).
const kitShell = decodeURIComponent(new URL('./resources/js/pages/Workspace/Partials/index.ts', import.meta.url).pathname);

export default defineConfig({ resolve: { alias: { '@kit/ui': kitUi, '@kit/shell': kitShell } }, plugins: [tailwindcss(), laravel({ input: ['resources/js/app.ts'], refresh: true }), vue({ script: { globalTypeFiles } })] });
