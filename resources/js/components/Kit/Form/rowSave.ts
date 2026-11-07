// Row autosave (docs/row-autosave.md), the pure part: each row of an editable table saves on its own, coalescing edits and carrying its version token.
// No Vue or Inertia here, so it runs in node tests; useRowAutosave.ts wraps it in reactive state and sends through Inertia's HTTP client.

export type RowVersion = string | number | null;
export type RowSaveState =
    'idle' | 'pending' | 'saving' | 'saved' | 'stale' | 'failed';
export type RowPatch = Record<string, unknown>;
/** A save's outcome: the new version (and the server's row, when it sends one), a conflict (409 or a stale_* / *conflict* code), or another failure. */
export type RowSaveResult =
    | { ok: true; version: RowVersion; row?: unknown }
    | { ok: false; conflict: boolean; reason?: string | null };
export type RowSaveSend = (
    id: string,
    patch: RowPatch,
    version: RowVersion,
) => Promise<RowSaveResult>;

type Row = {
    version: RowVersion;
    queued: RowPatch | null;
    timer: ReturnType<typeof setTimeout> | null;
    inFlight: boolean;
    state: RowSaveState;
    reason: string | null;
};

/**
 * One saver per table. `change(id, patch)` merges the patch into the row's queue and saves after `debounce` ms (0 saves on the next tick); `flush(id)` saves now
 * (on blur). At most one request per row is in flight: edits made meanwhile wait and go out together, with the version the reply returned. A conflict marks the
 * row `stale` and holds its edits unsent until `reload(id, version)` takes the server's copy; a failure marks it `failed` and keeps the edits for `retry(id)`.
 * Rows never wait on each other. `onChange` reports every state change, with the server's row on a save that returned one.
 */
export function createRowSaver(options: {
    send: RowSaveSend;
    debounce?: number;
    version?: (id: string) => RowVersion;
    onChange?: (
        id: string,
        state: RowSaveState,
        detail: { reason: string | null; row?: unknown; version: RowVersion },
    ) => void;
}) {
    const rows = new Map<string, Row>();
    const debounce = Math.max(0, options.debounce ?? 600);
    const row = (id: string): Row => {
        let entry = rows.get(id);
        if (!entry) {
            entry = {
                version: options.version?.(id) ?? null,
                queued: null,
                timer: null,
                inFlight: false,
                state: 'idle',
                reason: null,
            };
            rows.set(id, entry);
        }
        return entry;
    };
    const report = (
        id: string,
        entry: Row,
        state: RowSaveState,
        reason: string | null = null,
        server?: unknown,
    ) => {
        entry.state = state;
        entry.reason = reason;
        options.onChange?.(id, state, {
            reason,
            row: server,
            version: entry.version,
        });
    };
    async function send(id: string): Promise<void> {
        const entry = row(id);
        if (entry.timer) {
            clearTimeout(entry.timer);
            entry.timer = null;
        }
        if (entry.inFlight || entry.queued === null || entry.state === 'stale')
            return;
        const patch = entry.queued;
        entry.queued = null;
        entry.inFlight = true;
        report(id, entry, 'saving');
        let result: RowSaveResult;
        try {
            result = await options.send(id, patch, entry.version);
        } catch {
            result = { ok: false, conflict: false };
        }
        entry.inFlight = false;
        if (result.ok) {
            entry.version = result.version;
            if (entry.queued !== null) {
                report(id, entry, 'pending', null, result.row);
                return send(id);
            }
            report(id, entry, 'saved', null, result.row);
            return;
        }
        // Nothing is lost: the failed patch goes back under any edits made since.
        entry.queued = { ...patch, ...(entry.queued ?? {}) };
        report(
            id,
            entry,
            result.conflict ? 'stale' : 'failed',
            result.reason ?? null,
        );
    }
    function change(id: string, patch: RowPatch): void {
        const entry = row(id);
        entry.queued = { ...(entry.queued ?? {}), ...patch };
        if (entry.state === 'stale') return;
        if (!entry.inFlight) report(id, entry, 'pending');
        if (entry.timer) clearTimeout(entry.timer);
        entry.timer = setTimeout(() => {
            entry.timer = null;
            void send(id);
        }, debounce);
    }

    return {
        change,
        flush: (id: string) => send(id),
        retry: (id: string) => {
            const entry = row(id);
            if (entry.state === 'failed') {
                entry.state = 'pending';
                return send(id);
            }
            return Promise.resolve();
        },
        /** Takes the server's copy after a conflict: the new version, and the unsent edits dropped (the caller re-applies any it wants to keep). */
        reload: (id: string, version: RowVersion) => {
            const entry = row(id);
            if (entry.timer) clearTimeout(entry.timer);
            Object.assign(entry, { version, queued: null, timer: null });
            report(id, entry, 'idle');
        },
        forget: (id: string) => {
            const entry = rows.get(id);
            if (entry?.timer) clearTimeout(entry.timer);
            rows.delete(id);
        },
        state: (id: string): RowSaveState => rows.get(id)?.state ?? 'idle',
        /** Whether any row has edits not yet saved: queued, in flight, stale or failed. A leave guard reads this. */
        pending: () =>
            [...rows.values()].some(
                (entry) => entry.queued !== null || entry.inFlight,
            ),
    };
}

/** A refused save's outcome from its status and body: a conflict for 409 or a refusal code naming one (as the Workbench reads it), else a failure with the body's message. */
export function rowSaveFailure(
    status: number | undefined,
    body: unknown,
): RowSaveResult {
    const record =
        body !== null && typeof body === 'object'
            ? (body as Record<string, unknown>)
            : {};
    const code =
        typeof record.error === 'string'
            ? record.error
            : typeof record.code === 'string'
              ? record.code
              : null;
    const conflict =
        status === 409 ||
        (code !== null &&
            (code === 'stale_write' ||
                code.startsWith('stale_') ||
                code.includes('conflict')));
    const reason = typeof record.message === 'string' ? record.message : null;
    return { ok: false, conflict, reason };
}

/**
 * One save state for many rows, the Workbench's (`wb.syncSave(rollupRowSaves(states))`): a stale row is a conflict, then a failed row an error, then any row saving
 * is saving, any queued is unsaved, any saved is saved; all idle is idle.
 */
export function rollupRowSaves(
    states: RowSaveState[],
): 'idle' | 'unsaved' | 'saving' | 'saved' | 'conflict' | 'error' {
    if (states.includes('stale')) return 'conflict';
    if (states.includes('failed')) return 'error';
    if (states.includes('saving')) return 'saving';
    if (states.includes('pending')) return 'unsaved';
    return states.includes('saved') ? 'saved' : 'idle';
}
