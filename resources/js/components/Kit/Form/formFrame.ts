import { router, usePage } from '@inertiajs/vue3';
import {
    computed,
    inject,
    nextTick,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    watch,
    type InjectionKey,
    type MaybeRefOrGetter,
} from 'vue';
import { usePendingWork } from './pendingWork';
import {
    readCanSubmit,
    submitBlock,
    submitBlockMessages,
    type SubmitBlock,
} from './submitForm';

/*
 * What a form frame shares (FormActions, and a Workbench given `:form`): the form's status (busy, errors, unsaved changes, saved), the errors summary's
 * jump to the first invalid field, and the leave-page guard. One guard per page region: a frame inside another that already guards (a FormActions inside a
 * Workbench with `:form`) registers none of its own.
 */

/** A refusal code that names a conflict: `stale_write`, `stale_*` or `*conflict*` (the server answers a visit's 409 as errors[<code>]). The same rule as workbench.ts's isConflict, which stays standalone. */
export function isConflictCode(code: unknown): boolean {
    return (
        typeof code === 'string' &&
        (code === 'stale_write' ||
            code.startsWith('stale_') ||
            code.includes('conflict'))
    );
}

/** The conflict a form holds: a useSubmitForm's `conflict`, else the first error keyed by a conflict code (a useForm visit refused with 409). */
export function formConflict(
    form: FormLike | null | undefined,
): { code: string; message: string } | null {
    if (form?.conflict)
        return { code: 'stale_write', message: form.errors?._form ?? '' };
    for (const [code, message] of Object.entries(form?.errors ?? {}))
        if (message && isConflictCode(code)) return { code, message };
    return null;
}

// Methods, not function-typed properties: method parameters are bivariant, so a typed useForm<T>(), whose reset and clearErrors take only T's keys, is accepted.
export interface FormLike {
    processing: boolean;
    isDirty: boolean;
    hasErrors: boolean;
    recentlySuccessful: boolean;
    progress?: { percentage?: number } | null;
    errors?: Record<string, string | undefined>;
    warnOnLeave?: boolean;
    conflict?: boolean;
    canSubmit?: boolean;
    reset(...fields: string[]): void;
    clearErrors?(...fields: string[]): void;
    resetAndClearErrors?(): void;
}
export type FormFrameState =
    'busy' | 'errors' | 'dirty' | 'saved' | 'idle' | 'readonly';
export type FormFrameLabels = {
    busyLabel: string;
    savedLabel: string;
    dirtyLabel: string;
    errorsLabel: string;
    upToDateLabel: string;
};

/** Status, one at a time: read-only (a locked submit with a reason), busy, errors, unsaved changes, saved, else idle. */
export function useFormFrameStatus(
    source: () => FormLike | undefined,
    options: {
        labels: () => FormFrameLabels;
        locked?: () => boolean;
        reason?: () => string | null | undefined;
    },
) {
    const processing = computed(() => Boolean(source()?.processing));
    const progress = computed(() => source()?.progress?.percentage ?? null);
    const state = computed<FormFrameState>(() => {
        if (options.locked?.() && options.reason?.()) return 'readonly';
        if (processing.value) return 'busy';
        if (source()?.hasErrors) return 'errors';
        if (source()?.isDirty) return 'dirty';
        if (source()?.recentlySuccessful) return 'saved';
        return 'idle';
    });
    // Unsaved changes, whatever the status shows: errors don't make an edit any less unsaved, so leaving warns on this, not on the status.
    // A form showing errors holds input its failed submit didn't save, even where <Form> re-reads its defaults after the error response.
    const dirty = computed(() =>
        Boolean(source()?.isDirty || source()?.hasErrors),
    );
    const statusText = computed(() => {
        const labels = options.labels();
        if (state.value === 'busy')
            return progress.value !== null
                ? `Uploading… ${Math.round(progress.value)}%`
                : labels.busyLabel;
        return {
            errors: formConflict(source())?.message || labels.errorsLabel,
            dirty: labels.dirtyLabel,
            saved: labels.savedLabel,
            idle: labels.upToDateLabel,
            readonly: options.reason?.() ?? '',
        }[state.value];
    });
    const statusClass = computed(
        () =>
            ({
                errors: 'text-destructive-text',
                dirty: 'text-muted-foreground',
                saved: 'text-success-text',
                busy: 'text-muted-foreground',
                idle: '',
                readonly: 'text-muted-foreground',
            })[state.value],
    );
    return { processing, progress, state, dirty, statusText, statusClass };
}

/**
 * Whether Save can succeed now (submitForm.ts's submitBlock): not while pending work is in flight, a stale-write conflict is open (`conflict`, by default the
 * form's own), or the host's `canSubmit` (the frame's prop, or a useSubmitForm's option) is false. `blocked` is the reason, `reason` its text from the kit's `ui`
 * lang file; `describedBy` is the id the frame gives the reason's element, for the Save button's aria-describedby.
 */
export function useSubmitGate(
    source: () => FormLike | undefined,
    canSubmit: () => MaybeRefOrGetter<boolean> | null | undefined,
    id: string,
    conflict: () => boolean = () => formConflict(source()) !== null,
) {
    const work = usePendingWork();
    let shared: unknown;
    try {
        shared = (usePage().props as { kitUi?: { form?: unknown } }).kitUi
            ?.form;
    } catch {
        shared = undefined;
    }
    const blocked = computed<SubmitBlock | null>(() =>
        submitBlock({
            pending: work.pending.value,
            conflict: conflict(),
            canSubmit:
                source()?.canSubmit !== false && readCanSubmit(canSubmit()),
        }),
    );
    const reason = computed(() =>
        blocked.value ? submitBlockMessages(shared)[blocked.value] : null,
    );
    return {
        blocked,
        reason,
        describedBy: computed(() => (blocked.value ? id : undefined)),
    };
}

/** The field a summary jumps to: the control named by the first error's key, else the first control marked invalid. `_form` names no field. */
export function firstInvalidField(
    scopes: (ParentNode | null | undefined)[],
    errors: Record<string, unknown> | null | undefined,
): HTMLElement | null {
    const keys = Object.keys(errors ?? {}).filter(
        (key) => key !== '_form' && errors?.[key],
    );
    for (const key of keys) {
        for (const scope of scopes) {
            const named = scope?.querySelector<HTMLElement>(
                `[name="${CSS.escape(key)}"], [data-field="${CSS.escape(key)}"] [aria-invalid="true"], #${CSS.escape(key)}`,
            );
            if (named) return named;
        }
    }
    for (const scope of scopes) {
        const invalid = scope?.querySelector<HTMLElement>(
            '[aria-invalid="true"]',
        );
        if (invalid) return invalid;
    }
    return null;
}

/**
 * Focuses the first invalid field, after `reveal` has shown the pane it sits in (a collapsed inspector, a phone tab, a closed drawer). `reveal` gets the field
 * when it is in the DOM (or null when it is not rendered yet) and may return a promise; the field is looked up again after it. Returns the focused field.
 */
export async function focusFirstInvalid(
    scopes: () => (ParentNode | null | undefined)[],
    errors: Record<string, unknown> | null | undefined,
    reveal?: (field: HTMLElement | null) => unknown,
): Promise<HTMLElement | null> {
    const before = firstInvalidField(scopes(), errors);
    if (reveal) {
        await reveal(before);
        await nextTick();
        await new Promise((resolve) =>
            requestAnimationFrame(() => resolve(null)),
        );
    }
    const field = firstInvalidField(scopes(), errors) ?? before;
    field?.focus({ preventScroll: false });
    field?.scrollIntoView?.({
        block: 'center',
        behavior: 'instant' as ScrollBehavior,
    });
    return field;
}

// Whether an enclosing frame is guarding right now (a getter: a Workbench without `:form` guards nothing, so a FormActions inside it keeps its own).
const GUARDED: InjectionKey<() => boolean> = Symbol('kit.form-leave-guard');

type NavigateEvent = Event & {
    navigationType: string;
    cancelable: boolean;
    destination: { key: string };
};
type NavigationApi = EventTarget & { traverseTo(key: string): unknown };

/**
 * The leave-page guard. While the page is unsaved (`dirty`, or any pending work: pendingWork.ts, which BulkDrop's uploads join), leaving asks first in the
 * frame's Dialog (`askLeave` opens it, `leave()` goes on, closing it stays):
 * - an Inertia GET visit to another page (a partial reload, a visit to this path and any other method go through);
 * - Back and Forward: through the Navigation API where the browser has it (a same-document traverse is cancelled, then traversed to on Leave); elsewhere
 *   through one extra history entry for this page while unsaved (Back lands on the entry below it with the page kept, and asks; Leave goes back once more).
 *   The entry is taken off again (history.back()) when the page is clean, when a save starts, and before a confirmed visit, so none is left behind;
 * - closing or reloading the tab, through beforeunload (the browser's own prompt).
 * The save's own follow-up goes through: from the form's submit event (or `processing` turning on) until `processing` is off and every visit started meanwhile
 * (Inertia's redirect, a visit from onSuccess or after `await form.submit()`) has finished, the guard is saving and holds no Inertia visit.
 * `enabled` false (warnOnLeave false on the frame, or on a useSubmitForm) registers none of it. Inside a frame that is guarding it registers nothing: one guard,
 * one dialog.
 */
export function useLeaveGuard(
    dirty: () => boolean,
    enabled: () => boolean,
    ownForm: () => Element | null | undefined,
    processing: () => boolean = () => false,
) {
    const outer = inject(GUARDED, null);
    const active = () => !outer?.() && enabled();
    provide(GUARDED, active);
    const work = usePendingWork();
    const unsaved = () => dirty() || work.pending.value;
    const leaving = ref(false);
    const askLeave = ref(false);
    const saving = ref(false);
    const mounted = ref(false);
    const navigation =
        typeof window === 'undefined'
            ? undefined
            : (window as Window & { navigation?: NavigationApi }).navigation;
    // What Leave does; `history` when it moves through the guard's own entry itself (the fallback's Back).
    let proceed: { go: () => void; history?: boolean } | null = null;
    let visits = 0;
    let sawProcessing = false;
    let settle: ReturnType<typeof setTimeout> | undefined;
    let offRouter: (() => void)[] = [];
    let form: Element | null | undefined;

    // Saving: the submit and its follow-up. It ends a task after `processing` is off with no visit in flight, so a visit started in onSuccess counts.
    function startSaving() {
        saving.value = true;
        sawProcessing = processing();
        clearTimeout(settle);
        // A submit refused before it starts (the browser's validation, a handler that never processes) stops saving on its own.
        settle = setTimeout(() => {
            if (!sawProcessing && !processing() && visits === 0)
                saving.value = false;
        }, 250);
    }
    function checkSaved() {
        clearTimeout(settle);
        settle = setTimeout(() => {
            if (saving.value && !processing() && visits === 0)
                saving.value = false;
        }, 0);
    }
    const onSubmit = () => startSaving();
    watch(processing, (now) => {
        if (now) {
            if (!saving.value) startSaving();
            sawProcessing = true;
        } else if (saving.value) checkSaved();
    });

    // The history fallback (no Navigation API). While unsaved the page's entry is split in two: the entry below carries a null state, the one on top the page's
    // own. Inertia's popstate listener runs first (it was registered first) and treats a null state as "this page", rewriting that entry with the page and
    // scrolling to the top, never re-rendering; any other state would re-render the page and lose the edits. So Back lands on the null entry with the page
    // kept, the guard gives that entry the page's state back, and asks: Stay splits again (the old top is dropped as the new one is pushed), Leave goes back once more. Taking the guard off steps back
    // onto the lower entry the same way, so nothing is left behind. The scroll position Inertia resets is put back.
    let sentinel = false;
    let restoring = false;
    let afterRestore: (() => void) | null = null;
    // The page's own state, which the lower entry gets back whenever the guard lands on it.
    let pageState: unknown = null;
    let scroll: {
        x: number;
        y: number;
        regions: [Element, number, number][];
    } | null = null;
    const wantSentinel = () =>
        !navigation &&
        mounted.value &&
        active() &&
        unsaved() &&
        !leaving.value &&
        !saving.value &&
        visits === 0;
    const remember = () => {
        scroll = {
            x: window.scrollX,
            y: window.scrollY,
            regions: Array.from(
                document.querySelectorAll('[scroll-region]'),
                (el): [Element, number, number] => [
                    el,
                    el.scrollLeft,
                    el.scrollTop,
                ],
            ),
        };
    };
    function putScrollBack() {
        if (!scroll) return;
        window.scrollTo(scroll.x, scroll.y);
        for (const [el, left, top] of scroll.regions) {
            el.scrollLeft = left;
            el.scrollTop = top;
        }
    }
    function dropSentinel(then?: () => void) {
        sentinel = false;
        restoring = true;
        afterRestore = then ?? null;
        remember();
        window.history.back();
    }
    function syncSentinel() {
        if (restoring) return;
        if (wantSentinel() && !sentinel) {
            pageState = window.history.state;
            window.history.replaceState(null, '', window.location.href);
            window.history.pushState(pageState, '', window.location.href);
            sentinel = true;
            remember();
        } else if (!wantSentinel() && sentinel) {
            dropSentinel();
        }
    }
    watch(wantSentinel, () => syncSentinel());
    const onScroll = () => {
        if (sentinel) remember();
    };
    function popstate(event: PopStateEvent) {
        if (navigation) return;
        // On the lower entry: give it the page's state back (Inertia skips its own rewrite when its page has not changed), so a later Forward or Back to it
        // shows this page.
        if (event.state === null && (restoring || sentinel))
            window.history.replaceState(pageState, '', window.location.href);
        if (restoring) {
            restoring = false;
            putScrollBack();
            const then = afterRestore;
            afterRestore = null;
            if (then) then();
            else syncSentinel();
            return;
        }
        if (!sentinel || leaving.value || event.state !== null) return;
        // Back from the top entry onto the null one: the page is still here. Ask; Stay splits the entry again (wantSentinel, once the dialog closes).
        sentinel = false;
        putScrollBack();
        proceed = { go: () => window.history.back(), history: true };
        askLeave.value = true;
    }
    function navigate(event: Event) {
        const visit = event as NavigateEvent;
        if (
            leaving.value ||
            !active() ||
            visit.navigationType !== 'traverse' ||
            !visit.cancelable ||
            !(unsaved() || saving.value)
        )
            return;
        visit.preventDefault();
        const key = visit.destination.key;
        proceed = { go: () => navigation?.traverseTo(key) };
        askLeave.value = true;
    }

    const unload = (event: BeforeUnloadEvent) => {
        if (unsaved() && !leaving.value) {
            event.preventDefault();
            event.returnValue = '';
        }
    };
    function leave() {
        leaving.value = true;
        askLeave.value = false;
        const next = proceed;
        proceed = null;
        if (!next) return;
        if (sentinel && !next.history) dropSentinel(next.go);
        else next.go();
    }
    // Stay: forget the way out, and (the fallback) split the entry again.
    watch(askLeave, (open) => {
        if (!open && !leaving.value) {
            proceed = null;
            syncSentinel();
        }
    });
    onMounted(() => {
        form = ownForm();
        form?.addEventListener('submit', onSubmit, true);
        if (!active()) return;
        offRouter = [
            router.on('before', (event) => {
                const visit = event.detail.visit;
                if (
                    leaving.value ||
                    saving.value ||
                    (visit.only?.length ?? 0) > 0
                )
                    return;
                if (
                    unsaved() &&
                    visit.method === 'get' &&
                    visit.url.pathname !== window.location.pathname
                ) {
                    proceed = { go: () => router.visit(visit.url.href) };
                    askLeave.value = true;
                    return false;
                }
                // Anything else goes through; the guard's entry is taken off first, so the visit's own entry does not land on top of it.
                if (sentinel) dropSentinel();
            }),
            // A new page: a guard that outlived the visit (a persistent layout) guards it afresh.
            router.on('navigate', () => {
                leaving.value = false;
            }),
            router.on('start', (event) => {
                if (!(event.detail.visit?.only?.length > 0)) visits++;
            }),
            router.on('finish', (event) => {
                if (event.detail.visit?.only?.length > 0) return;
                visits = Math.max(0, visits - 1);
                if (saving.value) checkSaved();
                else syncSentinel();
            }),
        ];
        window.addEventListener('popstate', popstate, true);
        window.addEventListener('scroll', onScroll, {
            capture: true,
            passive: true,
        });
        navigation?.addEventListener('navigate', navigate);
        mounted.value = true;
    });
    watch(
        () => active() && unsaved(),
        (now) => {
            if (now) window.addEventListener('beforeunload', unload);
            else window.removeEventListener('beforeunload', unload);
        },
        { immediate: true },
    );
    onBeforeUnmount(() => {
        clearTimeout(settle);
        form?.removeEventListener('submit', onSubmit, true);
        offRouter.forEach((off) => off());
        window.removeEventListener('beforeunload', unload);
        window.removeEventListener('popstate', popstate, true);
        window.removeEventListener('scroll', onScroll, true);
        navigation?.removeEventListener('navigate', navigate);
    });
    return { askLeave, leave, guarding: computed(() => active()) };
}
