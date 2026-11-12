import {
    computed,
    getCurrentInstance,
    markRaw,
    onBeforeUnmount,
    reactive,
    ref,
    shallowRef,
    watch,
    type Component,
} from 'vue';
import {
    createInspectorRegistry,
    createRecalculator,
    createReorder,
    DEFAULT_ZOOM,
    EMPTY,
    isConflict,
    isEmptyWorkbench,
    moveItem,
    pruneSelection,
    scaleFor,
    select as selectStep,
    selectElement as selectElementStep,
    selectMode,
    stepItem,
    stepZoom,
    type InspectorContext,
    type InspectorScope,
    type ReorderResult,
    type SaveState,
    type SelectMode,
    type Selection,
    type WorkbenchElement,
    type WorkbenchItem,
    type WorkbenchMode,
    type WorkbenchRegion,
    type ZoomState,
} from './workbench';
import type { createLayoutStore } from './workbenchLayout';
import {
    DEFAULT_ELEMENT_ATTRIBUTE,
    elementAttributeName,
} from './workbenchStage';
import { sendOrder, type ReorderAction } from '../Data/sortable';

/*
 * One Workbench's state, shared by key: the page, its stage, its inspectors and the Workbench component all call useWorkbench(key) and get the same store, so the
 * stage can select and the inspector region follows. Selection is single or multiple (from the stage or the navigator), with the current item, next/prev and
 * pruning when items go away. `elements` is the stage's own selection inside the current item (blocks on a slide): `selectElement(s)` and `clearElements`, never
 * pruned by the items, cleared when the current item changes, and it picks the inspector before the items do. `dirty` and `saveState` drive the actions bar. `reordering` is true while a reorder is in flight (the bar's and the navigator's actions are busy
 * meanwhile); a host write that sends `version` awaits `whenIdle()` first, so it carries the token the reorder's reply set. `onRecalculate` is the live preview with a stale guard. `defineInspector` registers
 * an inspector per open-ended type, and `inspector` is the one for the current selection. `reorder` applies a new navigator order optimistically through the
 * app's action and rolls back on a conflict, with a toast. `zoom`, `fit` and `scale` are the stage's, remembered with the layout.
 */
type Layout = ReturnType<typeof createLayoutStore>;

/** A client-side toast through the shell's Toaster, for kit components that act without a page visit (a refused reorder). Pages still never toast. */
export function showToast(
    type: 'success' | 'info' | 'warning' | 'error',
    message: string,
    description?: string,
): void {
    if (typeof window === 'undefined') return;
    const id = `client-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
    window.dispatchEvent(
        new CustomEvent('kit:toast:show', {
            detail: {
                id,
                type,
                message,
                description,
                style: 'plain',
                duration: type === 'success' || type === 'info' ? 4000 : null,
            },
        }),
    );
}

// The reorder action and its transport are the kit's shared ones (Kit/Data/sortable).
export { sendOrder, type ReorderAction } from '../Data/sortable';

function createWorkbench(key: string) {
    const items = shallowRef<WorkbenchItem[]>([]);
    const selection = ref<Selection>({ ...EMPTY });
    const order = computed(() => items.value.map((item) => item.id));
    // No items yet: the Workbench stage renders its `#empty` slot instead of `#stage`.
    const isEmpty = computed(() => isEmptyWorkbench(items.value));
    const byId = computed(
        () => new Map(items.value.map((item) => [item.id, item])),
    );
    const current = computed(() => selection.value.anchor);
    const currentItem = computed(() =>
        current.value === null ? null : (byId.value.get(current.value) ?? null),
    );
    const selected = computed(() => selection.value.ids);
    const multiple = ref(true);
    const elements = shallowRef<WorkbenchElement[]>([]);
    const paneCalls = ref(0);
    // The attribute each stage element carries its id in (workbenchStage's selectedRects reads it).
    const elementAttribute = ref(DEFAULT_ELEMENT_ATTRIBUTE);
    // Elements belong to the current item: switching items (or the current one going away) clears them.
    watch(
        current,
        () => {
            elements.value = [];
        },
        { flush: 'sync' },
    );

    const dirty = ref(false);
    const saveState = ref<SaveState>('idle');
    // When the last save landed, for the bar's "Saved · 2 min ago".
    const savedAt = ref<number | null>(null);

    const registry = createInspectorRegistry<Component>();
    const registryVersion = ref(0);
    const inspector = computed(() => {
        void registryVersion.value;
        return registry.resolve(
            selection.value.ids,
            (id) => byId.value.get(id)?.type ?? null,
            current.value,
            elements.value,
        );
    });

    const version = ref<string | number | null>(null);
    const reorderAction = ref<ReorderAction | null>(null);

    const zoom = ref<ZoomState>({ ...DEFAULT_ZOOM });
    const content = ref({ width: 0, height: 0 });
    const viewport = ref({ width: 0, height: 0 });
    const scale = computed(() =>
        scaleFor(zoom.value, content.value, viewport.value),
    );
    let layout: Layout | null = null;

    const mode = ref<WorkbenchMode>('columns');
    const tab = ref<WorkbenchRegion>('stage');
    const drawerOpen = ref(false);
    const collapsed = reactive<Partial<Record<WorkbenchRegion, boolean>>>({});
    const panes = new Map<WorkbenchRegion, () => void>();
    const focusers = new Map<WorkbenchRegion, () => void>();

    function setItems(next: WorkbenchItem[]): void {
        items.value = next;
        selection.value = pruneSelection(
            selection.value,
            next.map((item) => item.id),
        );
    }
    function select(
        id: string,
        how:
            | SelectMode
            | {
                  shiftKey?: boolean;
                  metaKey?: boolean;
                  ctrlKey?: boolean;
              } = 'replace',
    ): void {
        const step =
            typeof how === 'string'
                ? multiple.value
                    ? how
                    : 'replace'
                : selectMode(how, multiple.value);
        selection.value = selectStep(selection.value, id, step, order.value);
    }
    function setSelection(ids: string[]): void {
        const known = ids.filter((id) => byId.value.has(id));
        selection.value = {
            ids: multiple.value ? known : known.slice(-1),
            anchor: known[known.length - 1] ?? null,
        };
    }
    const clear = () => {
        selection.value = { ...EMPTY };
    };
    function selectElements(next: WorkbenchElement[]): void {
        const seen = new Set<string>();
        const unique = next.filter(
            (element) =>
                typeof element?.id === 'string' &&
                typeof element.type === 'string' &&
                !seen.has(element.id) &&
                seen.add(element.id),
        );
        elements.value = multiple.value ? unique : unique.slice(-1);
    }
    function selectElement(
        id: string,
        type: string,
        how:
            | SelectMode
            | {
                  shiftKey?: boolean;
                  metaKey?: boolean;
                  ctrlKey?: boolean;
              } = 'replace',
    ): void {
        const step =
            typeof how === 'string'
                ? multiple.value
                    ? how
                    : 'replace'
                : selectMode(how, multiple.value);
        elements.value = selectElementStep(elements.value, { id, type }, step);
    }
    const clearElements = () => {
        elements.value = [];
    };
    const step = (direction: 1 | -1): string | null => {
        const id = stepItem(order.value, current.value, direction);
        if (id !== null) select(id, 'replace');
        return id;
    };

    // A reorder in flight, reactive: the bar's and the navigator's actions wait for it, and a host write awaits whenIdle(), since the version token only moves
    // when the reorder's reply arrives (a write sent before then carries the old one and is refused as stale).
    const reordering = ref(false);
    let idle: (() => void)[] = [];
    const whenIdle = (): Promise<void> =>
        reordering.value
            ? new Promise((resolve) => {
                  idle.push(resolve);
              })
            : Promise.resolve();
    const reorderer = createReorder({
        get: () => order.value,
        set: (ids) => {
            const map = byId.value;
            items.value = ids
                .map((id) => map.get(id))
                .filter((item): item is WorkbenchItem => !!item);
        },
        version: () => version.value,
        send: (ids, token) =>
            reorderAction.value
                ? sendOrder(reorderAction.value, ids, token)
                : Promise.resolve({ ok: true as const, version: token }),
        saved: (next) => {
            version.value = next;
        },
        busy: (value) => {
            reordering.value = value;
            if (!value) {
                const waiting = idle;
                idle = [];
                waiting.forEach((resolve) => resolve());
            }
        },
        rolledBack: (reason) =>
            showToast(
                'error',
                reason === 'conflict'
                    ? 'The order changed elsewhere, so yours was not saved.'
                    : 'The new order could not be saved.',
                reason === 'conflict'
                    ? 'Reload to see the latest order.'
                    : undefined,
            ),
    });

    function setZoom(next: ZoomState): void {
        zoom.value = next;
        void layout?.update({ zoom: next });
    }

    return reactive({
        key,
        items,
        order,
        isEmpty,
        selection,
        selected,
        current,
        currentItem,
        multiple,
        setItems,
        select,
        setSelection,
        clear,
        /** The stage elements selected inside the current item; read only, changed through selectElements / selectElement / clearElements. */
        get elements(): readonly WorkbenchElement[] {
            return elements.value;
        },
        selectElements,
        selectElement,
        clearElements,
        /** The attribute a stage element carries its id in: `data-workbench-element` unless the host names another. */
        get elementAttribute(): string {
            return elementAttribute.value;
        },
        setElementAttribute(name: string | null | undefined) {
            elementAttribute.value = elementAttributeName(name);
        },
        next: () => step(1),
        prev: () => step(-1),

        dirty,
        saveState,
        savedAt,
        reordering,
        whenIdle,
        markDirty() {
            dirty.value = true;
            saveState.value = 'unsaved';
        },
        markSaving() {
            saveState.value = 'saving';
        },
        markSaved() {
            dirty.value = false;
            saveState.value = 'saved';
            savedAt.value = Date.now();
        },
        /** Follows a rolled-up state (rollupRowSaves over useRowAutosave's rows): each change calls the matching mark. */
        syncSave(state: SaveState) {
            if (state === saveState.value) return;
            ({
                idle: () => {
                    saveState.value = 'idle';
                },
                unsaved: () => {
                    dirty.value = true;
                    saveState.value = 'unsaved';
                },
                saving: () => {
                    saveState.value = 'saving';
                },
                saved: () => {
                    dirty.value = false;
                    saveState.value = 'saved';
                    savedAt.value = Date.now();
                },
                conflict: () => {
                    saveState.value = 'conflict';
                },
                error: () => {
                    saveState.value = 'error';
                },
            })[state]();
        },
        markConflict() {
            saveState.value = 'conflict';
        },
        /**
         * Keep my edits after a conflict (the person compared and chose theirs): clears the form's conflict (a useSubmitForm's `conflict` through its
         * `keepMine()`, or each conflict-coded error of a useForm: `stale_write`, `stale_*`, `*conflict*`), keeps its values, and puts the bar back to
         * Unsaved changes, so Save is enabled and the next save sends these values. Without a conflict it does nothing.
         */
        keepMine(
            form?: {
                errors?: Record<string, unknown>;
                clearErrors?: (...fields: string[]) => void;
                conflict?: boolean;
                keepMine?: () => void;
            } | null,
        ) {
            if (form) {
                if (typeof form.keepMine === 'function') form.keepMine();
                else if (typeof form.clearErrors === 'function') {
                    const codes = Object.keys(form.errors ?? {}).filter(
                        (code) => isConflict(null, code),
                    );
                    if (codes.length) form.clearErrors(...codes);
                }
            }
            if (saveState.value === 'conflict') {
                dirty.value = true;
                saveState.value = 'unsaved';
            }
        },
        markFailed() {
            saveState.value = 'error';
        },

        /**
         * Live preview: `request(input)` runs `fn` after `debounce` ms of quiet (250 by default). A newer request aborts the older run's signal, and a result that arrives
         * after a newer run started is dropped. Cancelled when the calling component unmounts.
         */
        onRecalculate<I, R>(
            fn: (input: I, signal: AbortSignal) => Promise<R>,
            options: {
                debounce?: number;
                apply: (result: R) => void;
                failed?: (error: unknown) => void;
            },
        ) {
            const recalculator = createRecalculator(fn, options);
            if (getCurrentInstance())
                onBeforeUnmount(() => recalculator.cancel());
            return recalculator;
        },

        /**
         * Registers the inspector for a type (a list of types makes it a common inspector for a mixed selection); `@document` and `@fallback` are reserved.
         * `for: 'elements' | 'items'` scopes it to stage elements or navigator items, so the two can share a type name; `any` (the default) serves both. Returns the unregister function.
         */
        defineInspector(
            type: string | string[],
            component: Component,
            options: {
                when?: (context: InspectorContext) => boolean;
                for?: InspectorScope;
            } = {},
        ): () => void {
            const remove = registry.define(type, markRaw(component), options);
            registryVersion.value++;
            const unregister = () => {
                remove();
                registryVersion.value++;
            };
            if (getCurrentInstance()) onBeforeUnmount(unregister);
            return unregister;
        },
        inspector,

        version,
        setReorder(
            action: ReorderAction | null,
            token: string | number | null,
        ) {
            reorderAction.value = action;
            version.value = token;
        },
        /** Applies a new navigator order (ids) optimistically; false when it rolled back or another reorder is still saving. */
        reorder: (ids: string[]) => reorderer.apply(ids),
        /** Moves the current item one place down (1) or up (-1). */
        moveCurrent(direction: 1 | -1): Promise<boolean> {
            const at =
                current.value === null
                    ? -1
                    : order.value.indexOf(current.value);
            return at === -1
                ? Promise.resolve(false)
                : reorderer.apply(moveItem(order.value, at, at + direction));
        },

        zoom,
        scale,
        setZoom,
        fit: (how: 'width' | 'page') =>
            setZoom({ fit: how, zoom: scale.value }),
        zoomIn: () => setZoom(stepZoom(scale.value, 1)),
        zoomOut: () => setZoom(stepZoom(scale.value, -1)),
        zoomReset: () => setZoom({ fit: null, zoom: 1 }),
        /** The stage reports its content's natural size and the space it has; `scale` follows when a fit is on. */
        measure(
            size: { width: number; height: number },
            space: { width: number; height: number },
        ) {
            content.value = size;
            viewport.value = space;
        },
        attachLayout(store: Layout | null) {
            layout = store;
            if (store?.layout.zoom) zoom.value = store.layout.zoom;
        },

        mode,
        tab,
        drawerOpen,
        collapsed,
        /** Workbench registers how to collapse a pane and focus a region; pages call togglePane / focusRegion. */
        registerPane(region: WorkbenchRegion, toggle: () => void) {
            panes.set(region, toggle);
            return () => panes.delete(region);
        },
        registerFocus(region: WorkbenchRegion, focus: () => void) {
            focusers.set(region, focus);
            return () => focusers.delete(region);
        },
        togglePane(region: WorkbenchRegion) {
            paneCalls.value++;
            panes.get(region)?.();
        },
        focusRegion(region: WorkbenchRegion) {
            paneCalls.value++;
            focusers.get(region)?.();
        },
        /** How many times a pane was toggled or a region focused through the store (the host's own controls, or the Workbench's keys). */
        get paneCalls(): number {
            return paneCalls.value;
        },
    });
}

export type WorkbenchStore = ReturnType<typeof createWorkbench>;
const stores = new Map<string, { store: WorkbenchStore; users: number }>();

/**
 * The store for `key`, created on first use and dropped when the last component using it unmounts (outside a component it lives until `releaseWorkbench`).
 * The key is the Workbench's `workbench-key`: one per kind of workbench (`deck-editor`), not per record. `elementSelector` names the attribute the stage's
 * elements carry their id in (`data-block-id`, or `[data-block-id]`), as `<Workbench element-attribute>` does.
 */
export function useWorkbench(
    key: string,
    options: { elementSelector?: string | null } = {},
): WorkbenchStore {
    let entry = stores.get(key);
    if (!entry) {
        entry = { store: createWorkbench(key), users: 0 };
        stores.set(key, entry);
    }
    if (options.elementSelector)
        entry.store.setElementAttribute(options.elementSelector);
    if (getCurrentInstance()) {
        entry.users++;
        const held = entry;
        onBeforeUnmount(() => {
            held.users--;
            if (held.users <= 0 && stores.get(key) === held) stores.delete(key);
        });
    }
    return entry.store;
}

/** Drops a store made outside a component (tests, a store created ahead of the page). */
export function releaseWorkbench(key: string): void {
    stores.delete(key);
}
