import type { Method } from '@inertiajs/core';

/**
 * SplitPane's storage for Reka's Splitter (its `storage` option): the layout state only, never files or form contents. Reading prefers the server's `state`, then a
 * guarded localStorage fallback. A changed value goes to the server preference descriptor as `{ key, value }`; with no descriptor, a rejected write or a network failure
 * it falls back to localStorage. Writes are not debounced, ordered or retried.
 */
export type SplitPreference = { url: string; method: string };
export type SplitSend = (preference: SplitPreference, body: { key: string; value: string }) => Promise<boolean>;
type Local = Pick<Storage, 'getItem' | 'setItem'>;

export function splitFallbackKey(scope: string, key: string): string {
    return `kit:split:${scope}:${key}`;
}

export function splitStorage(options: { key: string; scope: string; state?: string | null; preference?: SplitPreference | null; send?: SplitSend; local?: () => Local | undefined; saved?: (value: string) => void }) {
    let serialized = options.state ?? null;
    const fallbackKey = splitFallbackKey(options.scope, options.key);
    const local = options.local ?? (() => globalThis.localStorage);
    const fallback = (value: string) => { try { local()?.setItem(fallbackKey, value); } catch { /* Storage may be blocked. */ } };
    return {
        getItem: (): string | null => {
            if (serialized !== null) return serialized;
            try { return local()?.getItem(fallbackKey) ?? null; } catch { return null; }
        },
        setItem: (_key: string, value: string): void => {
            if (serialized === value) return;
            serialized = value;
            const preference = options.preference;
            if (!preference) { fallback(value); return; }
            (options.send ?? sendPreference)(preference, { key: options.key, value })
                .then(ok => { if (ok) options.saved?.(value); else fallback(value); }, () => fallback(value));
        },
    };
}

/**
 * A standalone request through Inertia's HTTP client (not a page visit, so it stays separate from the form's): the host's `http` interceptors and
 * client apply, and the client sends Laravel's XSRF token. A non-2xx response or a network failure resolves false. Imported on first save, so the storage loads without Inertia.
 */
export const sendPreference: SplitSend = async (preference, body) => {
    try {
        const { http } = await import('@inertiajs/vue3');
        const response = await http.getClient().request({ method: preference.method.toLowerCase() as Method, url: preference.url, data: body, headers: { Accept: 'application/json' } });
        return response.status >= 200 && response.status < 300;
    } catch {
        return false;
    }
};
