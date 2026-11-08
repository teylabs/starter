import type { Plugin } from 'vite';

// Types for kitImports.js, the dev-server plugin that loads `@kit/ui` names per file (docs/kit-imports.md).
export interface KitImportsOptions {
    /** The barrel's import specifier; `@kit/ui` by default. */
    specifier?: string;
}

export function kitImports(options?: KitImportsOptions): Plugin;

export default kitImports;
