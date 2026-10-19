import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// tey/kit pages type their props with the generated global ViewModel types; the SFC compiler resolves them from here.
const globalTypeFiles = [decodeURIComponent(new URL('./resources/js/types.generated.d.ts', import.meta.url).pathname)];

export default defineConfig({ plugins: [tailwindcss(), laravel({ input: ['resources/js/app.ts'], refresh: true }), vue({ script: { globalTypeFiles } })] });
