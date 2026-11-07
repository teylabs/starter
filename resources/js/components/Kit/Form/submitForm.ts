import { reactive, toValue, type MaybeRefOrGetter } from 'vue';
import { errorJson, responseJson } from '../http';

/*
 * Prefer useForm / <Form>: pure Inertia means your own UI saves through a visit. useSubmitForm is for the allowed non-Inertia targets only (a third-party
 * or public API, upload signing, a socket or run transport, typeahead); see docs/submit-form.md.
 *
 * useSubmitForm(initial, handler, options?): a form whose save is the host's own async handler (a fetch, useHttp, a socket), never an Inertia visit. It never
 * touches the router. The result is what FormActions, FormCard and Workbench read from a useForm() (processing, isDirty, hasErrors, recentlySuccessful,
 * progress, reset, clearErrors), plus `data`, `errors`, `submit()`, `setDefaults()` and `conflict`.
 *
 * submit() calls the handler with the data (through `transform` when given) as `Promise.resolve().then(() => handler(data))`, so a synchronous throw counts as a
 * failure too. A submit while one is processing is ignored. On success the defaults move to the submitted data (isDirty is a deep compare against them), the
 * `resetOnSuccess` fields go back to their defaults from before the submit, and `recentlySuccessful` is true for about 2 seconds. A failure is read in this order:
 *   1. a SubmitError: its field messages, and its summary as `_form`;
 *   2. a thrown fetch Response, or an error with a `response` (Inertia's useHttp / HttpResponseError), read through http.ts: a 422 whose `errors` is Laravel's shape
 *      maps to the fields; a 409 `{ error: 'stale_write' }` sets `conflict` (and a `_form` line); any other status is the generic `_form` message;
 *   3. a thrown plain object of Laravel's 422 shape (field => list of strings) maps to the fields;
 *   4. anything else, and any `errors` that is not exactly that shape, is the generic `_form` message.
 * Only the first message per field is kept, as plain text capped at SUBMIT_MESSAGE_MAX characters: the kit renders it as text, never as HTML.
 * `timeout` (ms, off by default) fails a handler that has not settled by then with `_form` "Timed out." (the handler itself is not cancelled).
 * `warnOnLeave` false opts this form out of the leave-page guard FormActions and the Workbench register; the default is unchanged (they guard).
 * `canSubmit` (a boolean, a Ref or a getter; true by default) is the host's own "this can be saved now": while it is false, submit() resolves false without calling
 * the handler, and FormActions and the Workbench disable Save, ignore ⌘S and Enter, and say why (submitBlock below, with pending work and an open conflict).
 *
 * httpSubmit(request) is the handler for Inertia's useHttp. useHttp does not reject on a 422: it calls `onError` and resolves undefined, so awaiting it alone reads
 * a refused save as a success. httpSubmit hands `request` the hooks to pass to the useHttp call and turns its outcomes into the contract above: `onError` (a 422)
 * rejects with a SubmitError of the field errors; a thrown HttpResponseError (a 409 `stale_write`, any other status) and a network failure reject as thrown; a
 * resolve of undefined with no `onError` (the hooks not passed) rejects with the generic message. Only a resolved response (its data, null for an empty body) succeeds.
 */

/** The longest message kept from a failure; longer text is cut and ends with an ellipsis. */
export const SUBMIT_MESSAGE_MAX = 300;
export const SUBMIT_GENERIC_MESSAGE = 'This could not be saved. Try again.';
export const SUBMIT_TIMEOUT_MESSAGE = 'Timed out. Try again.';
export const SUBMIT_CONFLICT_MESSAGE =
    'Someone else changed this. Reload to see their version before you save again.';
const SUCCESS_MS = 2000;

/** A client-side refusal from a handler: `throw new SubmitError({ title: 'Required' }, 'Fix the title.')`, with no request made. */
export class SubmitError extends Error {
    readonly errors: Record<string, string>;
    readonly summary: string | null;
    constructor(errors: Record<string, string>, summary?: string) {
        super(summary ?? 'The form has errors.');
        this.name = 'SubmitError';
        this.errors = errors;
        this.summary = summary ?? null;
    }
}

export type SubmitFormErrors<T> = Partial<
    Record<(keyof T & string) | '_form', string>
>;
export type SubmitFormOptions<T, P = T> = {
    /** Milliseconds before an unsettled handler fails with "Timed out."; off by default. */
    timeout?: number;
    /** After a success, put every field (true) or only these back to the defaults they had before the submit. */
    resetOnSuccess?: boolean | (keyof T & string)[];
    /** The payload the handler receives, from a copy of the data. */
    transform?: (data: T) => P;
    /** false: no leave-page guard for this form. */
    warnOnLeave?: boolean;
    /** The host's own condition for saving (a boolean, a Ref or a getter); while false, submit() is refused and Save is disabled. True by default. */
    canSubmit?: MaybeRefOrGetter<boolean>;
};
export interface SubmitForm<T> {
    data: T;
    errors: SubmitFormErrors<T>;
    readonly processing: boolean;
    readonly isDirty: boolean;
    readonly hasErrors: boolean;
    readonly recentlySuccessful: boolean;
    readonly progress: null;
    /** The last submit was refused as a stale write (409 `stale_write`). */
    readonly conflict: boolean;
    readonly warnOnLeave: boolean;
    /** The `canSubmit` option, read now. */
    readonly canSubmit: boolean;
    /** Resolves true on success, false on a failure, when a submit is already processing, or while `canSubmit` is false. Never rejects. */
    submit(): Promise<boolean>;
    reset(...fields: (keyof T & string)[]): void;
    clearErrors(...fields: ((keyof T & string) | '_form')[]): void;
    /**
     * Sets an error by hand, as Inertia's useForm does: one field and its message, or several at once. The message is shown as plain text, capped like a
     * server's; an empty message clears that field. Other fields keep their errors. A later submit replaces them all, as usual.
     */
    setError(field: (keyof T & string) | '_form', message: string): void;
    setError(errors: SubmitFormErrors<T>): void;
    resetAndClearErrors(): void;
    /** With no argument the current data becomes the defaults; with values, those fields' defaults. */
    setDefaults(values?: Partial<T>): void;
}

/** The hooks httpSubmit gives the request; pass them in the useHttp call's options: `http.post(url, hooks)` or `{ ...hooks, headers }`. */
export type HttpSubmitHooks = {
    /** useHttp's 422 callback: field => message (or a list of messages, with withAllErrors()). */
    onError: (errors: Record<string, string | string[]>) => void;
};

/**
 * A useSubmitForm handler over a useHttp request: `useSubmitForm(initial, httpSubmit((data, hooks) => http.transform(() => data).post(url, hooks)))`.
 * Fails closed: it resolves only with what useHttp resolved for a 2xx, never on a 422, an error, a network failure or a resolve with no response.
 */
export function httpSubmit<P = unknown, R = unknown>(
    request: (
        data: P,
        hooks: HttpSubmitHooks,
    ) => Promise<R | undefined> | R | undefined,
): (data: P) => Promise<R> {
    return async (data: P) => {
        let refused: Record<string, string> | null = null;
        const hooks: HttpSubmitHooks = {
            onError: (errors) => {
                refused = {};
                if (!isPlainObject(errors)) return;
                for (const [field, value] of Object.entries(errors)) {
                    const text =
                        typeof value === 'string' ? value : first(value);
                    if (text !== null) refused[field] = text;
                }
            },
        };
        const result = await request(data, hooks);
        if (refused !== null) throw new SubmitError(refused);
        if (result === undefined) throw new SubmitError({});
        return result;
    };
}

const BRAND = Symbol('kit.submit-form');

/** Whether a form is a useSubmitForm() result (FormCard and Workbench submit it themselves), not an Inertia useForm(). */
export function isSubmitForm(
    form: unknown,
): form is SubmitForm<Record<string, unknown>> {
    return (
        typeof form === 'object' &&
        form !== null &&
        (form as Record<symbol, unknown>)[BRAND] === true
    );
}

export function useSubmitForm<T extends Record<string, unknown>, P = T>(
    initial: T,
    handler: (data: P) => Promise<unknown>,
    options: SubmitFormOptions<T, P> = {},
): SubmitForm<T> {
    let defaults = clone(initial);
    let successTimer: ReturnType<typeof setTimeout> | undefined;
    // Typed loosely inside (reactive() unwraps a generic T into a type TypeScript cannot relate back to T); the getters give the typed view.
    const state = reactive({
        data: clone(initial) as Record<string, unknown>,
        errors: {} as Record<string, string>,
        processing: false,
        recentlySuccessful: false,
        conflict: false,
        // Bumped when the defaults move, so isDirty recomputes.
        version: 0,
    });
    const form = {
        get data() {
            return state.data as T;
        },
        set data(value: T) {
            state.data = clone(value);
        },
        get errors() {
            return state.errors as SubmitFormErrors<T>;
        },
        set errors(value: SubmitFormErrors<T>) {
            state.errors = value as Record<string, string>;
        },
        get processing() {
            return state.processing;
        },
        get isDirty() {
            void state.version;
            return !deepEqual(state.data, defaults);
        },
        get hasErrors() {
            return Object.keys(state.errors).length > 0;
        },
        get recentlySuccessful() {
            return state.recentlySuccessful;
        },
        get progress() {
            return null;
        },
        get conflict() {
            return state.conflict;
        },
        get warnOnLeave() {
            return options.warnOnLeave !== false;
        },
        get canSubmit() {
            return readCanSubmit(options.canSubmit);
        },
        async submit(): Promise<boolean> {
            if (state.processing || !readCanSubmit(options.canSubmit))
                return false;
            state.processing = true;
            state.conflict = false;
            state.recentlySuccessful = false;
            clearTimeout(successTimer);
            state.errors = {};
            const submitted = clone(state.data) as T;
            let timer: ReturnType<typeof setTimeout> | undefined;
            try {
                const work = Promise.resolve().then(() =>
                    handler(
                        options.transform
                            ? options.transform(clone(submitted))
                            : (clone(submitted) as unknown as P),
                    ),
                );
                const timeout = options.timeout;
                const expiry = new Promise<never>((_, reject) => {
                    if (timeout && timeout > 0)
                        timer = setTimeout(() => reject(TIMED_OUT), timeout);
                });
                await (timeout && timeout > 0
                    ? Promise.race([work, expiry])
                    : work);
                const before = defaults;
                defaults = { ...before, ...clone(submitted) };
                const resetFields =
                    options.resetOnSuccess === true
                        ? Object.keys(before)
                        : Array.isArray(options.resetOnSuccess)
                          ? options.resetOnSuccess
                          : [];
                for (const field of resetFields) {
                    if (!(field in before)) continue;
                    (defaults as Record<string, unknown>)[field] = clone(
                        before[field],
                    );
                    state.data[field] = clone(before[field]);
                }
                state.version++;
                state.recentlySuccessful = true;
                successTimer = setTimeout(() => {
                    state.recentlySuccessful = false;
                }, SUCCESS_MS);
                return true;
            } catch (error) {
                const read = await readFailure(error);
                state.conflict = read.conflict;
                state.errors = read.errors;
                return false;
            } finally {
                clearTimeout(timer);
                state.processing = false;
            }
        },
        reset(...fields: (keyof T & string)[]) {
            const keys = fields.length ? fields : Object.keys(defaults);
            for (const field of keys)
                state.data[field] = clone(defaults[field]);
        },
        clearErrors(...fields: ((keyof T & string) | '_form')[]) {
            if (!fields.length) {
                state.errors = {};
                return;
            }
            const next = { ...state.errors };
            for (const field of fields) delete next[field];
            state.errors = next;
        },
        setError(
            field: SubmitFormErrors<T> | ((keyof T & string) | '_form'),
            text?: string,
        ) {
            const entries =
                typeof field === 'string'
                    ? [[field, text ?? '']]
                    : Object.entries(field);
            const next = { ...state.errors };
            for (const [key, value] of entries) {
                const plain = typeof value === 'string' ? message(value) : '';
                if (plain === '') delete next[key as string];
                else next[key as string] = plain;
            }
            state.errors = next;
        },
        resetAndClearErrors() {
            form.reset();
            form.clearErrors();
            state.conflict = false;
        },
        setDefaults(values?: Partial<T>) {
            defaults = values
                ? { ...defaults, ...clone(values) }
                : (clone(state.data) as T);
            state.version++;
        },
    };
    Object.defineProperty(form, BRAND, { value: true });
    return form as SubmitForm<T>;
}

/** A host's `canSubmit` (a boolean, a Ref or a getter) read now; anything but false allows the save. */
export function readCanSubmit(
    value: MaybeRefOrGetter<boolean> | null | undefined,
): boolean {
    return value === undefined || value === null || toValue(value) !== false;
}

/**
 * Why a form frame's Save cannot succeed now, first reason first: `pending` (pendingWork.ts: an upload in flight), `conflict` (an open 409 stale write), `host`
 * (canSubmit false); null when it can. While it is not null FormActions and the Workbench disable Save, ignore ⌘S and Enter, and describe the button with the
 * reason's text (submitBlockMessages).
 */
export type SubmitBlock = 'pending' | 'conflict' | 'host';
export function submitBlock(state: {
    pending: boolean;
    conflict: boolean;
    canSubmit: boolean;
}): SubmitBlock | null {
    if (state.pending) return 'pending';
    if (state.conflict) return 'conflict';
    if (!state.canSubmit) return 'host';
    return null;
}

/** The reasons' text: the kit's `ui` lang file (`form.submit_blocked.*`, shared as `kitUi.form`), else these. */
export const SUBMIT_BLOCK_MESSAGES: Record<SubmitBlock, string> = {
    pending: 'Waiting for uploads to finish',
    conflict: 'Reload to see the latest version before you save',
    host: "This can't be saved yet",
};
export function submitBlockMessages(
    shared: unknown,
): Record<SubmitBlock, string> {
    const blocked = isPlainObject(shared) ? shared.submit_blocked : undefined;
    const pick = (key: SubmitBlock) => {
        const text = isPlainObject(blocked) ? blocked[key] : undefined;
        return typeof text === 'string' && text.trim() !== ''
            ? text
            : SUBMIT_BLOCK_MESSAGES[key];
    };
    return {
        pending: pick('pending'),
        conflict: pick('conflict'),
        host: pick('host'),
    };
}

const TIMED_OUT = Symbol('kit.submit-timeout');

type Failure = { errors: Record<string, string>; conflict: boolean };

/** What a failed handler threw, as field errors and a conflict flag. Exported for the unit tests; the contract is in the header comment. */
export async function readFailure(error: unknown): Promise<Failure> {
    if (error === TIMED_OUT) return generic(SUBMIT_TIMEOUT_MESSAGE);
    if (error instanceof SubmitError) {
        const errors = fieldErrors(error.errors, (value) =>
            typeof value === 'string' ? value : null,
        );
        if (error.summary) errors._form = message(error.summary);
        return {
            errors: Object.keys(errors).length
                ? errors
                : { _form: SUBMIT_GENERIC_MESSAGE },
            conflict: false,
        };
    }
    const response = await responseOf(error);
    if (response) {
        const body = response.body as {
            errors?: unknown;
            error?: unknown;
        } | null;
        if (response.status === 422 && body && isLaravelErrors(body.errors))
            return { errors: fieldErrors(body.errors, first), conflict: false };
        if (response.status === 409 && body?.error === 'stale_write')
            return {
                errors: { _form: SUBMIT_CONFLICT_MESSAGE },
                conflict: true,
            };
        return generic(SUBMIT_GENERIC_MESSAGE);
    }
    if (isLaravelErrors(error))
        return { errors: fieldErrors(error, first), conflict: false };
    return generic(SUBMIT_GENERIC_MESSAGE);
}

/** Laravel's 422 `errors`: a non-empty plain object whose every value is a non-empty list of strings. */
export function isLaravelErrors(
    value: unknown,
): value is Record<string, string[]> {
    if (!isPlainObject(value)) return false;
    const entries = Object.entries(value);
    return (
        entries.length > 0 &&
        entries.every(
            ([, messages]) =>
                Array.isArray(messages) &&
                messages.length > 0 &&
                messages.every((text) => typeof text === 'string'),
        )
    );
}

/** Plain text, trimmed, capped at SUBMIT_MESSAGE_MAX characters. */
export function message(text: string): string {
    const plain = text.trim();
    return plain.length > SUBMIT_MESSAGE_MAX
        ? `${plain.slice(0, SUBMIT_MESSAGE_MAX - 1)}…`
        : plain;
}

const first = (messages: unknown): string | null =>
    Array.isArray(messages) && typeof messages[0] === 'string'
        ? messages[0]
        : null;
const generic = (text: string): Failure => ({
    errors: { _form: text },
    conflict: false,
});
const UNSAFE_KEYS = new Set(['__proto__', 'constructor', 'prototype']);

function fieldErrors(
    source: Record<string, unknown>,
    pick: (value: unknown) => string | null,
): Record<string, string> {
    const errors: Record<string, string> = {};
    for (const [field, value] of Object.entries(source)) {
        const text = pick(value);
        if (UNSAFE_KEYS.has(field) || text === null || text.trim() === '')
            continue;
        errors[field] = message(text);
    }
    return errors;
}

/** A thrown fetch Response (its body read as text), or an error carrying a `response` (useHttp's), as a status and a parsed body (null when not JSON). */
async function responseOf(
    error: unknown,
): Promise<{ status: number | undefined; body: unknown } | null> {
    if (isFetchResponse(error)) {
        let text = '';
        try {
            text = await (
                typeof error.clone === 'function' ? error.clone() : error
            ).text();
        } catch {
            text = '';
        }
        const type = error.headers?.get?.('content-type') ?? undefined;
        try {
            return {
                status: error.status,
                body: responseJson(
                    { data: text, headers: { 'content-type': type } },
                    { allowEmpty: true },
                ),
            };
        } catch {
            return { status: error.status, body: null };
        }
    }
    if (
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        (error as { response?: unknown }).response
    )
        return errorJson(error);
    return null;
}

type FetchLike = {
    status: number;
    text(): Promise<string>;
    clone?(): FetchLike;
    headers?: { get?(name: string): string | null };
};
function isFetchResponse(value: unknown): value is FetchLike {
    if (typeof Response !== 'undefined' && value instanceof Response)
        return true;
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as FetchLike).status === 'number' &&
        typeof (value as FetchLike).text === 'function'
    );
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    if (typeof value !== 'object' || value === null || Array.isArray(value))
        return false;
    const proto = Object.getPrototypeOf(value);
    return proto === Object.prototype || proto === null;
}

/** A deep copy of plain objects and arrays; anything else (a File, a Date) is kept by reference. */
function clone<V>(value: V): V {
    if (Array.isArray(value)) return value.map((item) => clone(item)) as V;
    if (isPlainObject(value)) {
        const copy: Record<string, unknown> = {};
        for (const [key, item] of Object.entries(value))
            copy[key] = clone(item);
        return copy as V;
    }
    return value;
}

function deepEqual(a: unknown, b: unknown): boolean {
    if (Object.is(a, b)) return true;
    if (a instanceof Date && b instanceof Date)
        return a.getTime() === b.getTime();
    if (Array.isArray(a) && Array.isArray(b))
        return (
            a.length === b.length &&
            a.every((item, index) => deepEqual(item, b[index]))
        );
    if (isPlainObject(a) && isPlainObject(b)) {
        const keys = Object.keys(a);
        return (
            keys.length === Object.keys(b).length &&
            keys.every(
                (key) =>
                    Object.prototype.hasOwnProperty.call(b, key) &&
                    deepEqual(a[key], b[key]),
            )
        );
    }
    return false;
}
