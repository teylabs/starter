/*
 * The Workbench's logic, without Vue or the DOM, so it can be tested on its own: the layout mode per width, the regions F6 cycles, selection (single, toggle and
 * range), the inspector registry, the live-preview recalculation with its stale guard, optimistic reorder with rollback, stage zoom and fit, and the keyboard map.
 * Workbench.vue and useWorkbench compose these; nothing here knows what an item is beyond its id, label and open-ended type.
 */
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';

// ── Layout ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

/** `columns` (xl and up: navigator, stage, inspector), `drawer` (lg: navigator and stage, the inspector in a right drawer) or `tabs` (below lg). */
export type WorkbenchMode = 'columns' | 'drawer' | 'tabs';
export type WorkbenchRegion = 'navigator' | 'stage' | 'inspector' | 'footer';

/** Tailwind's lg and xl, in pixels. */
export const LG = 1024;
export const XL = 1280;

export function workbenchMode(width: number): WorkbenchMode {
    return width >= XL ? 'columns' : width >= LG ? 'drawer' : 'tabs';
}

export type WorkbenchLabels = Record<WorkbenchRegion, string>;
export const DEFAULT_LABELS: WorkbenchLabels = {
    navigator: 'Navigator',
    stage: 'Stage',
    inspector: 'Inspector',
    footer: 'Notes',
};

/** The phone tabs: Navigator, Stage, Inspector, and the footer's only when the page provides one. */
export function workbenchTabs(
    footer: boolean,
    labels: WorkbenchLabels = DEFAULT_LABELS,
): { value: WorkbenchRegion; label: string }[] {
    const regions: WorkbenchRegion[] = footer
        ? ['navigator', 'stage', 'inspector', 'footer']
        : ['navigator', 'stage', 'inspector'];
    return regions.map((value) => ({ value, label: labels[value] }));
}

/** The regions F6 moves between, in reading order: a collapsed pane, a closed drawer and a missing footer are skipped. */
export function regionOrder(
    mode: WorkbenchMode,
    state: {
        footer: boolean;
        collapsed?: Partial<Record<WorkbenchRegion, boolean>>;
        drawerOpen?: boolean;
    },
): WorkbenchRegion[] {
    const collapsed = state.collapsed ?? {};
    const order: WorkbenchRegion[] = [];
    if (mode === 'tabs')
        return workbenchTabs(state.footer).map((tab) => tab.value);
    if (!collapsed.navigator) order.push('navigator');
    order.push('stage');
    if (state.footer && !collapsed.footer) order.push('footer');
    if (mode === 'columns' ? !collapsed.inspector : state.drawerOpen)
        order.push('inspector');
    return order;
}

/** The region after (or, with `back`, before) `current`, wrapping; the first one when `current` is not in the order. */
export function nextRegion(
    order: WorkbenchRegion[],
    current: WorkbenchRegion | null,
    back = false,
): WorkbenchRegion | null {
    if (!order.length) return null;
    const at = current === null ? -1 : order.indexOf(current);
    if (at === -1) return back ? order[order.length - 1] : order[0];
    return order[(at + (back ? -1 : 1) + order.length) % order.length];
}

// ── Selection ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

/** An item in the navigator. `actions` are its row menu's kit action descriptors (Move up, Duplicate, Delete…), as the host decides them per item; none, no menu. */
/**
 * A navigator item's picture: `src` and its `alt`. `alt` is required; send '' when the image only shows what the label already says (decorative), so the option's
 * name stays its label. Text in `alt` is added to the option's name, so give it only when the image says more than the label.
 */
export type WorkbenchThumbnail = { src: string; alt: string };
export type WorkbenchItem = {
    id: string;
    label: string;
    type?: string | null;
    description?: string | null;
    actions?: ActionDescriptor[];
    thumbnail?: WorkbenchThumbnail | null;
};
/** The navigator's rows: `list` (one line each, the default) or `filmstrip` (a slides app's left rail: number, a 16:9 thumbnail and the label, stacked). */
export type WorkbenchNavigatorLayout = 'list' | 'filmstrip';
/** Selected ids in selection order; `anchor` is the current item (the last one chosen), from which a range extends. */
/** Whether the workspace has no items yet: the stage then shows the `#empty` slot (or a quiet EmptyState) in place of `#stage`. The one definition of "empty". */
export function isEmptyWorkbench(
    items: readonly WorkbenchItem[] | null | undefined,
): boolean {
    return !items || items.length === 0;
}
/** The `#empty` slot's props: the Workbench's action descriptors (`actions`), `act` to run one through its `action` event, and the stage's label. */
export type WorkbenchEmptySlotProps = {
    actions: ActionDescriptor[];
    act: (action: ActionDescriptor) => void;
    label: string;
};
/** The `#navigator-item` slot's props: the item, whether it is selected, and its index in the listed (filtered) items. */
export type WorkbenchNavigatorItemSlotProps = {
    item: WorkbenchItem;
    selected: boolean;
    index: number;
};
/** The `#navigator-thumbnail` slot's props (filmstrip only): the item, its index in the listed (filtered) items, and whether it is selected. */
export type WorkbenchNavigatorThumbnailSlotProps = {
    item: WorkbenchItem;
    index: number;
    selected: boolean;
};
/** The default empty stage's title, when the host gives no `#empty` slot: neutral, with no actions. */
export const EMPTY_STAGE_TITLE = 'Nothing here yet.';

export type Selection = { ids: string[]; anchor: string | null };
export type SelectMode = 'replace' | 'toggle' | 'range';
export const EMPTY: Selection = { ids: [], anchor: null };

/**
 * One selection step. `replace` selects only `id`; `toggle` adds or removes it (⌘/Ctrl-click); `range` selects from the anchor to `id` in `order` (Shift-click),
 * keeping the anchor. An id not in `order` is ignored by `range`, which falls back to `replace`.
 */
export function select(
    selection: Selection,
    id: string,
    mode: SelectMode,
    order: string[],
): Selection {
    if (mode === 'toggle') {
        if (selection.ids.includes(id)) {
            const ids = selection.ids.filter((other) => other !== id);
            return {
                ids,
                anchor:
                    selection.anchor === id
                        ? (ids[ids.length - 1] ?? null)
                        : selection.anchor,
            };
        }
        return { ids: [...selection.ids, id], anchor: id };
    }
    if (mode === 'range' && selection.anchor !== null) {
        const from = order.indexOf(selection.anchor);
        const to = order.indexOf(id);
        if (from !== -1 && to !== -1) {
            const ids = order.slice(Math.min(from, to), Math.max(from, to) + 1);
            return {
                ids: from <= to ? ids : ids.reverse(),
                anchor: selection.anchor,
            };
        }
    }
    return { ids: [id], anchor: id };
}

/** The selection mode a pointer or key event asks for: Shift extends a range, ⌘ or Ctrl toggles, anything else replaces. */
export function selectMode(
    event: { shiftKey?: boolean; metaKey?: boolean; ctrlKey?: boolean },
    multiple = true,
): SelectMode {
    if (!multiple) return 'replace';
    return event.shiftKey
        ? 'range'
        : event.metaKey || event.ctrlKey
          ? 'toggle'
          : 'replace';
}

/** The selection with ids that no longer exist dropped (after the items change); the anchor moves to the last one left. */
export function pruneSelection(
    selection: Selection,
    order: string[],
): Selection {
    const ids = selection.ids.filter((id) => order.includes(id));
    if (ids.length === selection.ids.length) return selection;
    return {
        ids,
        anchor:
            selection.anchor !== null && ids.includes(selection.anchor)
                ? selection.anchor
                : (ids[ids.length - 1] ?? null),
    };
}

/** The item after (or before) the current one in navigator order, or null at either end; with nothing current, the first (or last). */
export function stepItem(
    order: string[],
    current: string | null,
    step: 1 | -1,
): string | null {
    if (!order.length) return null;
    if (current === null || !order.includes(current))
        return step === 1 ? order[0] : order[order.length - 1];
    return order[order.indexOf(current) + step] ?? null;
}

/** The distinct types of the selected items, in selection order; an item with no type counts as ''. */
export function selectionTypes(
    ids: string[],
    typeOf: (id: string) => string | null | undefined,
): string[] {
    return [...new Set(ids.map((id) => typeOf(id) ?? ''))];
}

/**
 * A stage element: something inside the current item (a block on a slide) that the stage selects. Element selection is the stage's own, apart from the item
 * selection: it is never pruned by the items, and it is cleared when the current item changes.
 */
export type WorkbenchElement = { id: string; type: string };

/** One element selection step: `replace` selects only `element`; `toggle` and `range` (⌘/Ctrl- or Shift-click on the stage) add it, or remove it when it is selected. */
export function selectElement(
    elements: WorkbenchElement[],
    element: WorkbenchElement,
    mode: SelectMode,
): WorkbenchElement[] {
    if (mode === 'replace') return [element];
    return elements.some((other) => other.id === element.id)
        ? elements.filter((other) => other.id !== element.id)
        : [...elements, element];
}

// ── Inspectors ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

/** Reserved inspector types: `@document` when nothing is selected, `@fallback` when no registered inspector covers the selection. Selected stage elements decide first, then the items. */
export const DOCUMENT = '@document';
export const FALLBACK = '@fallback';

/** `types` are the element types when stage elements are selected, else the selected items' types. `element` is the only selected element, when there is one. */
export type InspectorContext = {
    ids: string[];
    types: string[];
    current: string | null;
    elements: WorkbenchElement[];
    element?: WorkbenchElement;
};
/** What an inspector is for: stage elements, navigator items, or either (the default). Item and element types are separate namespaces when scoped. */
export type InspectorScope = 'elements' | 'items' | 'any';
export type InspectorEntry<C> = {
    types: string[];
    component: C;
    when?: (context: InspectorContext) => boolean;
    for: InspectorScope;
};
/**
 * `document`: nothing is selected; `single`: one type is selected (one item or several of it); `common`: mixed types, and one inspector covers them all;
 * `fallback`: nothing registered covers the selection. `component` is null when even the fallback is not registered.
 */
export type ResolvedInspector<C> = {
    kind: 'document' | 'single' | 'common' | 'fallback';
    type: string | null;
    component: C | null;
    context: InspectorContext;
};

/**
 * The inspector registry, keyed by open-ended type (a block type, a record type). `define(type, component, { when })` registers one; `type` may be a list, which makes
 * it a common inspector for a mixed selection of those types. Later definitions win over earlier ones for the same type, and a `when` that returns false skips it.
 * `for` keeps item and element types apart: element selections consider only `elements` and `any` definitions, item selections (and nothing selected) only
 * `items` and `any`, so a slide layout and a block type of the same name don't collide. `any` is the default.
 */
export function createInspectorRegistry<C>() {
    const entries: InspectorEntry<C>[] = [];
    const matching = (
        types: string[],
        context: InspectorContext,
        scope: 'elements' | 'items',
    ) => {
        for (let i = entries.length - 1; i >= 0; i--) {
            const entry = entries[i];
            if (
                (entry.for === 'any' || entry.for === scope) &&
                types.every((type) => entry.types.includes(type)) &&
                (entry.when?.(context) ?? true)
            )
                return entry;
        }
        return null;
    };
    return {
        define(
            type: string | string[],
            component: C,
            options: {
                when?: (context: InspectorContext) => boolean;
                for?: InspectorScope;
            } = {},
        ): () => void {
            const entry: InspectorEntry<C> = {
                types: Array.isArray(type) ? [...type] : [type],
                component,
                when: options.when,
                for: options.for ?? 'any',
            };
            if (
                !entry.types.length ||
                entry.types.some((t) => typeof t !== 'string' || t === '')
            )
                throw new Error('An inspector needs a type.');
            if (!['elements', 'items', 'any'].includes(entry.for))
                throw new Error(
                    "An inspector is for 'elements', 'items' or 'any'.",
                );
            entries.push(entry);
            return () => {
                const at = entries.indexOf(entry);
                if (at !== -1) entries.splice(at, 1);
            };
        },
        resolve(
            ids: string[],
            typeOf: (id: string) => string | null | undefined,
            current: string | null = null,
            elements: WorkbenchElement[] = [],
        ): ResolvedInspector<C> {
            const types = elements.length
                ? [...new Set(elements.map((element) => element.type))]
                : selectionTypes(ids, typeOf);
            const context: InspectorContext = {
                ids,
                types,
                current,
                elements,
                ...(elements.length === 1 ? { element: elements[0] } : {}),
            };
            const scope = elements.length ? 'elements' : 'items';
            if (!ids.length && !elements.length) {
                const document = matching([DOCUMENT], context, scope);
                return {
                    kind: 'document',
                    type: DOCUMENT,
                    component: document?.component ?? null,
                    context,
                };
            }
            const found = matching(types, context, scope);
            if (found)
                return {
                    kind: types.length === 1 ? 'single' : 'common',
                    type: types.length === 1 ? types[0] : null,
                    component: found.component,
                    context,
                };
            return {
                kind: 'fallback',
                type: FALLBACK,
                component:
                    matching([FALLBACK], context, scope)?.component ?? null,
                context,
            };
        },
        get size() {
            return entries.length;
        },
    };
}

// ── Live preview ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

type Timers = {
    set: (run: () => void, ms: number) => unknown;
    clear: (handle: unknown) => void;
};
const realTimers: Timers = {
    set: (run, ms) => setTimeout(run, ms),
    clear: (handle) => clearTimeout(handle as ReturnType<typeof setTimeout>),
};

/**
 * A debounced live-preview recalculation with a stale guard: `request(input)` waits `debounce` ms of quiet, then runs `fn`; a newer request aborts the older run's
 * signal, and a result that arrives after a newer run started is dropped, so an out-of-order response never overwrites a fresher one. `apply` gets each kept result.
 */
export function createRecalculator<I, R>(
    fn: (input: I, signal: AbortSignal) => Promise<R>,
    options: {
        debounce?: number;
        apply: (result: R) => void;
        failed?: (error: unknown) => void;
        timers?: Timers;
    },
) {
    const timers = options.timers ?? realTimers;
    const debounce = Math.max(0, options.debounce ?? 250);
    let issued = 0;
    let handle: unknown = null;
    let controller: AbortController | null = null;
    let running = 0;
    const state = { pending: false };
    function run(input: I): void {
        handle = null;
        const ticket = ++issued;
        controller?.abort();
        const current = new AbortController();
        controller = current;
        running++;
        Promise.resolve()
            .then(() => fn(input, current.signal))
            .then(
                (result) => {
                    if (ticket === issued && !current.signal.aborted)
                        options.apply(result);
                },
                (error) => {
                    if (ticket === issued && !current.signal.aborted)
                        options.failed?.(error);
                },
            )
            .finally(() => {
                running--;
                state.pending = handle !== null || running > 0;
            });
    }
    return {
        request(input: I): void {
            if (handle !== null) timers.clear(handle);
            state.pending = true;
            handle = timers.set(() => run(input), debounce);
        },
        /** Drops the waiting request and any result still on its way. */
        cancel(): void {
            if (handle !== null) timers.clear(handle);
            handle = null;
            issued++;
            controller?.abort();
            state.pending = running > 0;
        },
        get pending() {
            return state.pending;
        },
    };
}

// ── Reorder ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

/** `ids` with the item at `from` moved to `to` (both clamped). */
export function moveItem(ids: string[], from: number, to: number): string[] {
    if (from < 0 || from >= ids.length) return ids;
    const target = Math.max(0, Math.min(ids.length - 1, to));
    if (target === from) return ids;
    const next = [...ids];
    const [moved] = next.splice(from, 1);
    next.splice(target, 0, moved);
    return next;
}

export type ReorderResult =
    | { ok: true; version: string | number | null }
    | { ok: false; conflict: boolean };

/** Whether a failed save is a conflict: a 409, or a refusal code that names one (`stale_write`, `stale_*`, `*conflict*`). */
export function isConflict(
    status: number | null | undefined,
    error?: unknown,
): boolean {
    if (status === 409) return true;
    return (
        typeof error === 'string' &&
        (error === 'stale_write' ||
            error.startsWith('stale_') ||
            error.includes('conflict'))
    );
}

/**
 * Applies an order optimistically and sends it with the version token through the app's action; on a conflict or a failure the previous order comes back and
 * `rolledBack` says why (a toast is the caller's). One reorder at a time: a reorder while one is in flight is refused (returns false). `busy` reports each change of
 * the in-flight state, so a caller can hold its own writes (they carry the version token, which only moves when the reorder's reply arrives).
 */
export function createReorder(options: {
    get: () => string[];
    set: (ids: string[]) => void;
    version: () => string | number | null;
    send: (
        ids: string[],
        version: string | number | null,
    ) => Promise<ReorderResult>;
    saved?: (version: string | number | null) => void;
    rolledBack?: (reason: 'conflict' | 'failed') => void;
    busy?: (busy: boolean) => void;
}) {
    let busy = false;
    return {
        get busy() {
            return busy;
        },
        async apply(next: string[]): Promise<boolean> {
            if (busy) return false;
            const previous = options.get();
            if (
                next.length === previous.length &&
                next.every((id, i) => id === previous[i])
            )
                return true;
            busy = true;
            options.busy?.(true);
            options.set(next);
            try {
                const result = await options.send(next, options.version());
                if (result.ok) {
                    options.saved?.(result.version);
                    return true;
                }
                options.set(previous);
                options.rolledBack?.(result.conflict ? 'conflict' : 'failed');
                return false;
            } catch {
                options.set(previous);
                options.rolledBack?.('failed');
                return false;
            } finally {
                busy = false;
                options.busy?.(false);
            }
        },
    };
}

// ── Zoom and fit ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

/** `fit` is `width` or `page` (the scale follows the stage's size), or null for a fixed `zoom` (1 = 100%). */
export type ZoomState = { fit: 'width' | 'page' | null; zoom: number };
export const ZOOM_STEPS = [
    0.25, 0.33, 0.5, 0.67, 0.75, 0.8, 0.9, 1, 1.1, 1.25, 1.5, 1.75, 2, 2.5, 3,
    4,
];
export const MIN_ZOOM = ZOOM_STEPS[0];
export const MAX_ZOOM = ZOOM_STEPS[ZOOM_STEPS.length - 1];
export const DEFAULT_ZOOM: ZoomState = { fit: 'page', zoom: 1 };

export const clampZoom = (zoom: number): number =>
    Number.isFinite(zoom) ? Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, zoom)) : 1;

/** The scale that fits `content` into `viewport`: by width alone, or the whole page (the smaller of both ratios). 1 when either size is unknown. */
export function fitScale(
    fit: 'width' | 'page',
    content: { width: number; height: number },
    viewport: { width: number; height: number },
): number {
    if (!(
        content.width > 0 &&
        content.height > 0 &&
        viewport.width > 0 &&
        viewport.height > 0
    ))
        return 1;
    const width = viewport.width / content.width;
    return clampZoom(
        fit === 'width'
            ? width
            : Math.min(width, viewport.height / content.height),
    );
}

/** The scale the stage renders at. */
export function scaleFor(
    state: ZoomState,
    content: { width: number; height: number },
    viewport: { width: number; height: number },
): number {
    return state.fit
        ? fitScale(state.fit, content, viewport)
        : clampZoom(state.zoom);
}

/** One step in or out from the current scale (a fit becomes a fixed zoom), along ZOOM_STEPS. */
export function stepZoom(scale: number, direction: 1 | -1): ZoomState {
    const current = clampZoom(scale);
    const next =
        direction === 1
            ? ZOOM_STEPS.find((step) => step > current + 0.001)
            : [...ZOOM_STEPS].reverse().find((step) => step < current - 0.001);
    return { fit: null, zoom: next ?? current };
}

export const zoomLabel = (scale: number): string =>
    `${Math.round(clampZoom(scale) * 100)}%`;

// ── Keyboard ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

export type WorkbenchCommand =
    | 'save'
    | 'next'
    | 'prev'
    | 'move-down'
    | 'move-up'
    | 'collapse-navigator'
    | 'collapse-inspector'
    | 'focus-filter'
    | 'close-drawer'
    | 'clear-elements'
    | 'clear-selection'
    | 'next-region'
    | 'prev-region'
    | 'zoom-in'
    | 'zoom-out'
    | 'zoom-reset';
type KeyLike = {
    key: string;
    metaKey?: boolean;
    ctrlKey?: boolean;
    shiftKey?: boolean;
    altKey?: boolean;
};

/**
 * The command a key asks for when the Workbench's own keys are on, else null. With `keys` off (a live session or a transport: the app owns the keyboard) no
 * binding fires: not ↑/↓, j/k, Alt+↑/↓, `/`, F6, Esc, ⌘[ / ⌘] or zoom. Focus management and the region landmarks stay; only the bindings go.
 */
export function enabledCommand(
    enabled: boolean,
    event: KeyLike,
    context: Parameters<typeof workbenchCommand>[1],
): WorkbenchCommand | null {
    return enabled ? workbenchCommand(event, context) : null;
}

/**
 * The command a key asks for, or null. ↑/↓ and j/k step through the navigator (only there; Alt+↑/↓ moves the current item); ⌘[ / ⌘] collapse the navigator and the
 * inspector; `/` focuses the navigator filter; Esc closes the drawer, else clears the stage elements, else the item selection (not while typing); F6 (Shift+F6 back) cycles regions; ⌘+ / ⌘- / ⌘0 zoom the stage. ⌘ is Ctrl off a Mac. While
 * typing in a field only F6, Esc and the ⌘ shortcuts apply, so letters and arrows stay the field's. ⌘S saves only when the Workbench has a `:form` (`save`), and
 * never with Shift or Alt (⌘⇧S is a rich-text editor's strikethrough).
 */
export function workbenchCommand(
    event: KeyLike,
    context: {
        inNavigator: boolean;
        typing: boolean;
        drawerOpen?: boolean;
        elements?: boolean;
        selection?: boolean;
        save?: boolean;
    },
): WorkbenchCommand | null {
    const command = event.metaKey || event.ctrlKey;
    if (event.key === 'F6')
        return event.shiftKey ? 'prev-region' : 'next-region';
    if (event.key === 'Escape') {
        if (context.drawerOpen) return 'close-drawer';
        if (context.typing) return null;
        return context.elements
            ? 'clear-elements'
            : context.selection
              ? 'clear-selection'
              : null;
    }
    if (command && !event.altKey) {
        if (event.key.toLowerCase() === 's')
            return context.save && !event.shiftKey ? 'save' : null;
        if (event.key === '[') return 'collapse-navigator';
        if (event.key === ']') return 'collapse-inspector';
        if (event.key === '=' || event.key === '+') return 'zoom-in';
        if (event.key === '-' || event.key === '_') return 'zoom-out';
        if (event.key === '0') return 'zoom-reset';
        return null;
    }
    if (context.typing || command) return null;
    if (event.key === '/' && !event.shiftKey) return 'focus-filter';
    if (!context.inNavigator) return null;
    if (event.altKey && event.key === 'ArrowDown') return 'move-down';
    if (event.altKey && event.key === 'ArrowUp') return 'move-up';
    if (event.altKey) return null;
    if (event.key === 'ArrowDown' || event.key === 'j') return 'next';
    if (event.key === 'ArrowUp' || event.key === 'k') return 'prev';
    return null;
}

/** Whether a key on a navigator item opens its menu: Shift+F10 or the context-menu key, with no other modifier. */
export function opensItemMenu(event: KeyLike): boolean {
    if (event.metaKey || event.ctrlKey || event.altKey) return false;
    return (
        event.key === 'ContextMenu' || (event.key === 'F10' && !!event.shiftKey)
    );
}

/** The descriptors an item's row menu shows: its own `actions`, or none (no button, and the browser keeps its own context menu). */
export const itemActions = (
    item: Pick<WorkbenchItem, 'actions'>,
): ActionDescriptor[] => item.actions ?? [];

/** The menu button's accessible name, with the item's label: "Actions for Slide 3". */
export const itemMenuLabel = (
    item: Pick<WorkbenchItem, 'label'>,
    prefix = 'Actions for',
): string => `${prefix} ${item.label}`;

/**
 * Whether the document-level listener handles an Esc that didn't start inside the Workbench: after a click on a plain stage element focus is on <body>, so
 * the Workbench's own keydown never sees it. Handled when focus is on <body> or inside the Workbench; never from a field that takes typing, nor while a dialog
 * (other than the Workbench's own drawer) or a menu is open, whose Esc is theirs.
 */
export function documentEscape(context: {
    key: string;
    typing: boolean;
    activeIsBody: boolean;
    activeInWorkbench: boolean;
    overlayOpen: boolean;
}): boolean {
    return (
        context.key === 'Escape' &&
        !context.typing &&
        !context.overlayOpen &&
        (context.activeIsBody || context.activeInWorkbench)
    );
}

/** Whether focus is in something that takes typing (a text field, a select, an editable region), where single keys are the field's. */
export function isTyping(
    target: {
        tagName?: string;
        isContentEditable?: boolean;
        getAttribute?: (name: string) => string | null;
    } | null,
): boolean {
    if (!target) return false;
    const tag = (target.tagName ?? '').toLowerCase();
    if (target.isContentEditable) return true;
    if (tag === 'textarea' || tag === 'select') return true;
    if (tag !== 'input') return target.getAttribute?.('role') === 'textbox';
    const type = (target.getAttribute?.('type') ?? 'text').toLowerCase();
    return ![
        'button',
        'checkbox',
        'radio',
        'submit',
        'reset',
        'range',
        'color',
        'file',
        'image',
    ].includes(type);
}

// ── Save state ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

/** What the actions bar says: nothing yet, Saving…, Saved, Unsaved changes, a conflict, or a failed save. */
export type SaveState =
    'idle' | 'saving' | 'saved' | 'unsaved' | 'conflict' | 'error';
export const SAVE_LABELS: Record<Exclude<SaveState, 'idle'>, string> = {
    saving: 'Saving…',
    saved: 'Saved',
    unsaved: 'Unsaved changes',
    conflict: 'Changed elsewhere',
    error: 'Not saved',
};
/** The bar's state after an edit: a failed save ("Not saved") is unsaved changes again once the form is edited and dirty; every other state is kept. */
export function editedSaveState(state: SaveState, dirty: boolean): SaveState {
    return state === 'error' && dirty ? 'unsaved' : state;
}

/** "Saved · 2 min ago": the time since the last save, in the reader's locale, to the minute (under a minute reads "just now"). Null before any save. */
export function savedAgo(
    savedAt: number | null,
    now: number,
    locale?: string,
    justNow = 'just now',
): string | null {
    if (savedAt === null) return null;
    const minutes = Math.floor(Math.max(0, now - savedAt) / 60000);
    if (minutes < 1) return justNow;
    const format = new Intl.RelativeTimeFormat(locale, {
        numeric: 'auto',
        style: 'short',
    });
    return minutes < 60
        ? format.format(-minutes, 'minute')
        : format.format(-Math.floor(minutes / 60), 'hour');
}
