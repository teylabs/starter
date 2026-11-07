import { http } from '@inertiajs/vue3';
import { onBeforeUnmount, reactive, readonly } from 'vue';
import { errorJson, responseJson } from '@/components/Kit/http';
import { usePendingWork } from '@/components/Kit/Form/pendingWork';
import {
    createRowSaver,
    rowSaveFailure,
    type RowPatch,
    type RowSaveResult,
    type RowSaveState,
    type RowVersion,
} from '@/components/Kit/Form/rowSave';
import type { Method } from '@/components/Kit/types';

export type RowAutosaveOptions = {
    /** Where a row saves: its PATCH (or PUT) URL. The server answers 2xx with `{ version, row? }`, or 409 / a `stale_*` code with the stored row. */
    url: (id: string) => string;
    method?: 'patch' | 'put' | 'post';
    /** The row's current version token, sent as `version` with every save. */
    version: (id: string) => RowVersion;
    debounce?: number;
    /** The server's copy of a saved row (when the reply carries `row`), for totals or derived fields the server recomputed. */
    saved?: (id: string, row: unknown) => void;
};

/**
 * Row autosave for an editable table (docs/row-autosave.md): call `change(row.id, { field: value })` from a cell's input, `flush(row.id)` on blur. Each row saves
 * alone, never twice at once, with its version; `status[id]` drives RowSaveStatus. Unsaved rows join the page's pending work, so the leave guard asks first.
 */
export function useRowAutosave(options: RowAutosaveOptions) {
    const status = reactive<
        Record<string, { state: RowSaveState; reason: string | null }>
    >({});
    const work = usePendingWork();
    // One task held open while any row has unsaved edits, so the leave guard asks first; released when all are saved, and on unmount.
    let end: (() => void) | null = null;
    const hold = (pending: boolean) => {
        if (pending && end === null) end = work.begin();
        else if (!pending && end !== null) {
            end();
            end = null;
        }
    };
    const send = async (
        id: string,
        patch: RowPatch,
        version: RowVersion,
    ): Promise<RowSaveResult> => {
        try {
            const response = await http.getClient().request({
                method: (options.method ?? 'patch') as Method,
                url: options.url(id),
                data: { ...patch, version },
                headers: { Accept: 'application/json' },
            });
            const body = responseJson(response) as {
                version?: RowVersion;
                row?: unknown;
            };
            return { ok: true, version: body.version ?? null, row: body.row };
        } catch (error) {
            const { status: code, body } = errorJson(error);
            return rowSaveFailure(code, body);
        }
    };
    const saver = createRowSaver({
        send,
        debounce: options.debounce,
        version: options.version,
        onChange: (id, state, detail) => {
            status[id] = { state, reason: detail.reason };
            if (state === 'saved' && detail.row !== undefined)
                options.saved?.(id, detail.row);
            hold(saver.pending());
        },
    });
    onBeforeUnmount(() => hold(false));

    return { ...saver, status: readonly(status) };
}
