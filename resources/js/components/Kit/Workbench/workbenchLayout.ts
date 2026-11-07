import type { Method } from '@/components/Kit/types';
import { http } from '@inertiajs/vue3';
import { errorJson, responseJson } from '../http';
import { clampZoom, type ZoomState } from './workbench';

/**
 * The Workbench's remembered layout, per person and workbench key: each splitter group's state as Reka serializes it (`groups`, sizes and collapsed panes), the
 * stage zoom, and the phone tab. Layout state only, never content. The server's copy wins on load. A change goes to the kit's preference endpoint as
 * `{ layout, version }`; the endpoint refuses a stale version (409 `stale_write`, with its own layout and version), and the store then takes the server's copy,
 * puts this tab's own changes back on top and sends once more. Writes go one at a time, the latest winning. With no endpoint, a refused write or a network failure,
 * guarded localStorage keeps it.
 */
export type WorkbenchLayout = {
    groups?: Record<string, string>;
    zoom?: ZoomState;
    tab?: string;
};
export type LayoutPreference = { url: string; method: string };
export type LayoutState = { layout: WorkbenchLayout | null; version: number };
export type LayoutResponse =
    | { status: 'ok'; version: number }
    | { status: 'stale'; layout: WorkbenchLayout | null; version: number }
    | { status: 'failed' };
export type LayoutSend = (
    preference: LayoutPreference,
    body: { layout: WorkbenchLayout; version: number },
) => Promise<LayoutResponse>;
type Local = Pick<Storage, 'getItem' | 'setItem'>;

/**
 * The panes to collapse when a Workbench first shows a splitter group: those the host asks for in `defaultCollapsed`, but only while that group has no
 * remembered state (a person's own layout always wins, and the host never touches the kit's storage key).
 */
export function defaultCollapses<R extends string>(
    defaults: Partial<Record<R, boolean>> | null | undefined,
    layout: WorkbenchLayout,
    group: string,
    regions: readonly R[],
): R[] {
    if (!defaults || layout.groups?.[group]) return [];
    return regions.filter((region) => defaults[region] === true);
}

export const layoutFallbackKey = (key: string): string =>
    `kit:workbench:${key}`;

/** A layout read defensively: unknown keys and malformed values are dropped, so a bad stored copy never breaks the page. */
export function parseLayout(value: unknown): WorkbenchLayout {
    const source =
        typeof value === 'string'
            ? (() => {
                  try {
                      return JSON.parse(value);
                  } catch {
                      return null;
                  }
              })()
            : value;
    if (!source || typeof source !== 'object' || Array.isArray(source))
        return {};
    const raw = source as Record<string, unknown>;
    const layout: WorkbenchLayout = {};
    if (
        raw.groups &&
        typeof raw.groups === 'object' &&
        !Array.isArray(raw.groups)
    ) {
        const groups = Object.entries(
            raw.groups as Record<string, unknown>,
        ).filter(
            (entry): entry is [string, string] =>
                typeof entry[1] === 'string' && entry[1].length <= 2000,
        );
        if (groups.length) layout.groups = Object.fromEntries(groups);
    }
    const zoom = raw.zoom as Record<string, unknown> | undefined;
    if (
        zoom &&
        typeof zoom === 'object' &&
        (zoom.fit === 'width' || zoom.fit === 'page' || zoom.fit === null) &&
        typeof zoom.zoom === 'number'
    )
        layout.zoom = { fit: zoom.fit, zoom: clampZoom(zoom.zoom) };
    if (typeof raw.tab === 'string' && /^[a-z]{1,20}$/.test(raw.tab))
        layout.tab = raw.tab;
    return layout;
}

export function createLayoutStore(options: {
    key: string;
    state?: LayoutState | null;
    preference?: LayoutPreference | null;
    send?: LayoutSend;
    local?: () => Local | undefined;
    changed?: (layout: WorkbenchLayout) => void;
}) {
    const local = options.local ?? (() => globalThis.localStorage);
    const fallbackKey = layoutFallbackKey(options.key);
    const readLocal = (): WorkbenchLayout => {
        try {
            return parseLayout(local()?.getItem(fallbackKey) ?? null);
        } catch {
            return {};
        }
    };
    let layout: WorkbenchLayout = options.state?.layout
        ? parseLayout(options.state.layout)
        : readLocal();
    let version = options.state?.version ?? 0;
    // The fields this tab changed and the server has not yet acknowledged: put back on top of the server's copy after a stale write.
    let mine: WorkbenchLayout = {};
    let inFlight = false;
    let queued = false;
    const fallback = () => {
        try {
            local()?.setItem(fallbackKey, JSON.stringify(layout));
        } catch {
            /* Storage may be blocked. */
        }
    };
    const merge = (
        base: WorkbenchLayout,
        over: WorkbenchLayout,
    ): WorkbenchLayout => ({
        ...base,
        ...over,
        groups:
            base.groups || over.groups
                ? { ...base.groups, ...over.groups }
                : undefined,
    });
    const clean = (value: WorkbenchLayout): WorkbenchLayout =>
        Object.fromEntries(
            Object.entries(value).filter(([, v]) => v !== undefined),
        ) as WorkbenchLayout;

    async function flush(retried = false): Promise<void> {
        const preference = options.preference;
        if (!preference) {
            fallback();
            return;
        }
        if (inFlight) {
            queued = true;
            return;
        }
        inFlight = true;
        const sending = mine;
        let response: LayoutResponse;
        try {
            response = await (options.send ?? sendLayout)(preference, {
                layout,
                version,
            });
        } catch {
            response = { status: 'failed' };
        }
        inFlight = false;
        if (response.status === 'ok') {
            version = response.version;
            if (mine === sending) mine = {};
        } else if (response.status === 'stale' && !retried) {
            version = response.version;
            layout = clean(merge(parseLayout(response.layout), mine));
            options.changed?.(layout);
            await flush(true);
            return;
        } else {
            fallback();
        }
        if (queued) {
            queued = false;
            await flush();
        }
    }

    function update(change: WorkbenchLayout): Promise<void> {
        const next = clean(merge(layout, change));
        if (JSON.stringify(next) === JSON.stringify(layout))
            return Promise.resolve();
        layout = next;
        mine = clean(merge(mine, change));
        return flush();
    }

    return {
        get layout(): WorkbenchLayout {
            return layout;
        },
        get version(): number {
            return version;
        },
        update,
        /** Reka's Splitter `storage` for one group: its serialized state lives at `groups[autoSaveId]`. */
        splitter() {
            return {
                getItem: (id: string): string | null =>
                    layout.groups?.[id] ?? null,
                setItem: (id: string, value: string): void => {
                    void update({ groups: { [id]: value } });
                },
            };
        },
    };
}

/**
 * A standalone request through Inertia's HTTP client (not a page visit): the host's `http` interceptors apply and Laravel's XSRF token goes with it. 2xx is saved,
 * 409 is stale (with the server's layout and version), anything else or a network failure is failed. Bodies go through responseJson (Inertia v3 gives a string).
 */
const stale = (body: unknown) => {
    const data = (body ?? {}) as { version?: unknown; layout?: unknown };
    return {
        status: 'stale' as const,
        layout: parseLayout(data.layout),
        version: Number(data.version ?? 0),
    };
};

export const sendLayout: LayoutSend = async (preference, body) => {
    try {
        const response = await http.getClient().request({
            method: preference.method.toLowerCase() as Method,
            url: preference.url,
            data: body,
            headers: { Accept: 'application/json' },
        });
        if (response.status >= 200 && response.status < 300) {
            const data = responseJson(response, { allowEmpty: true }) as {
                version?: unknown;
            } | null;
            return {
                status: 'ok',
                version: Number(data?.version ?? body.version + 1),
            };
        }
        if (response.status === 409) return stale(errorJson({ response }).body);
        return { status: 'failed' };
    } catch (error) {
        const refused = errorJson(error);
        if (refused.status === 409) return stale(refused.body);
        return { status: 'failed' };
    }
};
