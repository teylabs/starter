// Dev-server only (docs/kit-imports.md): rewrites `import { Button, Page } from '@kit/ui'` into one import per file the barrel re-exports,
// so a page's first load requests the kit components it uses instead of every file the barrel names. The barrel stays the source of
// truth: the plugin reads whichever module `@kit/ui` resolves to (the kit's own index.ts or an app's replacement) and maps only the
// names it re-exports from a file. Anything else (a name it can't place, `import * as`, an `export *` it can't see into) keeps going
// through the barrel, so a name is never dropped, only left eager. Production builds don't need it (the bundler tree-shakes the
// barrel), so it applies to `vite serve` only and build output is unchanged.
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const reExport = /export\s+(type\s+)?\{([^}]*)\}\s*from\s*['"]([^'"]+)['"]/g;
const starExport = /export\s+(type\s+)?\*\s+from\s*['"]([^'"]+)['"]/g;
const localList = /export\s+(type\s+)?\{([^}]*)\}(?!\s*from)/g;
const localDeclaration =
    /export\s+(?:declare\s+)?(?:async\s+)?(?:const|let|var|function\*?|class|enum)\s+([\w$]+)/g;

// Maps each value name the barrel re-exports to { source, imported }, and lists its value `export * from` specifiers (an app's barrel that re-exports
// the kit's with `export * from '@/components/Kit'`). A name the barrel declares or exports itself maps to null: it stays in the barrel, and shadows a star's.
// Type-only exports are skipped: TypeScript already erased them from callers.
export function readBarrel(code, barrelPath) {
    const names = new Map();
    const text = code
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/^\s*\/\/.*$/gm, '');
    for (const [, typeOnly, list, from] of text.matchAll(reExport)) {
        if (typeOnly) {
            continue;
        }

        const source = from.startsWith('.')
            ? resolve(dirname(barrelPath), from)
            : from;

        for (const raw of list.split(',')) {
            const entry = raw.trim();

            if (entry === '' || /^type\s/.test(entry)) {
                continue;
            }

            const [imported, exported = imported] = entry
                .split(/\s+as\s+/)
                .map((part) => part.trim());
            names.set(exported, { source, imported });
        }
    }

    for (const [, typeOnly, list] of text.matchAll(localList)) {
        for (const raw of typeOnly ? [] : list.split(',')) {
            const entry = raw.trim();

            if (entry !== '' && !/^type\s/.test(entry)) {
                names.set(
                    entry
                        .split(/\s+as\s+/)
                        .pop()
                        .trim(),
                    null,
                );
            }
        }
    }

    for (const [, name] of text.matchAll(localDeclaration)) {
        names.set(name, null);
    }

    const stars = [...text.matchAll(starExport)]
        .filter(([, typeOnly]) => !typeOnly)
        .map(([, , from]) => from);

    return { names, stars };
}

function importLine(source, imported, local) {
    const from = JSON.stringify(source);

    if (imported === 'default') {
        return `import ${local} from ${from};`;
    }

    return `import { ${imported === local ? local : `${imported} as ${local}`} } from ${from};`;
}

// Rewrites one module's `@kit/ui` imports and re-exports; returns null when nothing changed.
export function rewriteImports(code, ast, specifier, barrel) {
    const edits = [];

    for (const node of ast.body) {
        if (node.source?.value !== specifier) {
            continue;
        }

        const isImport = node.type === 'ImportDeclaration';

        if (
            (!isImport && node.type !== 'ExportNamedDeclaration') ||
            node.importKind === 'type' ||
            node.exportKind === 'type'
        ) {
            continue;
        }

        const direct = [];
        const kept = [];

        for (const spec of node.specifiers) {
            const name = isImport
                ? (spec.imported?.name ?? spec.imported?.value)
                : (spec.local.name ?? spec.local.value);
            const local = isImport
                ? spec.local.name
                : (spec.exported.name ?? spec.exported.value);
            const target =
                name === undefined ? undefined : barrel.names.get(name);

            if (
                spec.type === 'ImportSpecifier' ||
                spec.type === 'ExportSpecifier'
            ) {
                if (target) {
                    direct.push({ ...target, local, name });
                    continue;
                }
            }

            kept.push(spec);
        }

        if (direct.length === 0) {
            continue;
        }

        const lines = [];

        if (isImport) {
            lines.push(
                ...direct.map(({ source, imported, local }) =>
                    importLine(source, imported, local),
                ),
            );
        } else {
            lines.push(
                ...direct.map(
                    ({ source, imported, local }) =>
                        `export { ${imported === local ? local : `${imported} as ${local}`} } from ${JSON.stringify(source)};`,
                ),
            );
        }

        if (kept.length > 0) {
            const parts = kept.map((spec) => code.slice(spec.start, spec.end));
            const head =
                kept[0].type === 'ImportDefaultSpecifier' ||
                kept[0].type === 'ImportNamespaceSpecifier'
                    ? parts.shift()
                    : null;
            const named = parts.length > 0 ? `{ ${parts.join(', ')} }` : '';
            const clause = [head, named].filter(Boolean).join(', ');
            lines.push(
                `${isImport ? 'import' : 'export'} ${clause} from ${JSON.stringify(specifier)};`,
            );
        }

        edits.push({ start: node.start, end: node.end, text: lines.join(' ') });
    }

    if (edits.length === 0) {
        return null;
    }

    let out = code;

    for (const edit of edits.reverse()) {
        out = out.slice(0, edit.start) + edit.text + out.slice(edit.end);
    }

    return out;
}

// A module's value exports as the plugin can place them: its own names first, then each `export * from` it can read. As in ESM, a name two stars
// bring in from different files is ambiguous and stays on the barrel, and so does every star-brought name when one star can't be read (it might
// bring the same name). A cycle contributes nothing.
async function exportsOf(context, files, path) {
    if (files.has(path)) {
        return new Map();
    }

    files.add(path);
    const { names, stars } = readBarrel(readFileSync(path, 'utf8'), path);
    const fromStars = new Map();
    const ambiguous = new Set();
    let opaque = false;

    for (const star of stars) {
        const resolved = await context.resolve(star, path);
        const file = resolved?.id.split('?')[0];

        if (
            !file ||
            resolved.external ||
            file.includes('/node_modules/') ||
            !/\.[cm]?[jt]s$/.test(file)
        ) {
            opaque = true;
            continue;
        }

        for (const [name, target] of await exportsOf(context, files, file)) {
            const seen = fromStars.get(name);

            if (seen === undefined) {
                fromStars.set(name, target);
            } else if (
                !seen ||
                !target ||
                seen.source !== target.source ||
                seen.imported !== target.imported
            ) {
                ambiguous.add(name);
            }
        }
    }

    const out = new Map(names);

    for (const [name, target] of fromStars) {
        if (!out.has(name)) {
            out.set(name, opaque || ambiguous.has(name) ? null : target);
        }
    }

    return out;
}

export function kitImports(options = {}) {
    const specifier = options.specifier ?? '@kit/ui';
    let barrel = null;

    return {
        name: 'tey-kit-imports',
        apply: 'serve',
        enforce: 'post',
        async transform(code, id) {
            if (
                !code.includes(specifier) ||
                id.includes('/node_modules/') ||
                id.startsWith('\0')
            ) {
                return null;
            }

            const resolved = await this.resolve(specifier, id);

            if (!resolved || resolved.external) {
                return null;
            }

            const barrelPath = resolved.id.split('?')[0];

            if (id.split('?')[0] === barrelPath) {
                return null;
            }

            if (barrel?.path !== barrelPath) {
                const files = new Set();
                barrel = {
                    path: barrelPath,
                    files,
                    names: await exportsOf(this, files, barrelPath),
                };
            }

            let ast;

            try {
                ast = this.parse(code);
            } catch {
                return null;
            }

            const out = rewriteImports(code, ast, specifier, barrel);

            return out === null ? null : { code: out, map: null };
        },
        handleHotUpdate({ file }) {
            if (barrel?.files.has(file)) {
                barrel = null;
            }
        },
    };
}

export default kitImports;
