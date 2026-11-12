<script setup lang="ts">
import { SplitterGroup, SplitterPanel, SplitterResizeHandle } from 'reka-ui';
import {
    computed,
    h,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
    type FunctionalComponent,
    type MaybeRefOrGetter,
} from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import Button from '@/components/Kit/Form/Button.vue';
import {
    focusFirstInvalid,
    formConflict,
    useFormFrameStatus,
    useLeaveGuard,
    useSubmitGate,
    type FormLike,
} from '@/components/Kit/Form/formFrame';
import { isSubmitForm } from '@/components/Kit/Form/submitForm';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import type { Method } from '@/components/Kit/types';
import PaneScroll from '@/components/Kit/Layout/PaneScroll.vue';
import PaneTabs from '@/components/Kit/Layout/PaneTabs.vue';
import PaneToggle from '@/components/Kit/Layout/PaneToggle.vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';
import { groundCard, useGroundSurface } from '@/components/Kit/Surface/surface';
import {
    DEFAULT_LABELS,
    documentEscape,
    editedSaveState,
    enabledCommand,
    isTyping,
    nextRegion,
    regionOrder,
    workbenchMode,
    workbenchTabs,
    type WorkbenchEmptySlotProps,
    type WorkbenchItem,
    type WorkbenchLabels,
    type WorkbenchNavigatorItemSlotProps,
    type WorkbenchNavigatorLayout,
    type WorkbenchNavigatorThumbnailSlotProps,
    type WorkbenchRegion,
} from './workbench';
import {
    createLayoutStore,
    defaultCollapses,
    type LayoutPreference,
    type LayoutState,
} from './workbenchLayout';
import { useWorkbench, type ReorderAction } from './useWorkbench';
import WorkbenchActions from './WorkbenchActions.vue';
import WorkbenchCollapsedBar from './WorkbenchCollapsedBar.vue';
import WorkbenchInspector from './WorkbenchInspector.vue';
import WorkbenchNavigator from './WorkbenchNavigator.vue';
import WorkbenchStage from './WorkbenchStage.vue';

/*
 * A multi-pane editing surface for a sequence of items with a live preview (slides and their blocks, a valuation and its comparables): `navigator` (the items),
 * `stage` (the work, with an optional sticky `toolbar` and an optional resizable `footer` under it), `inspector` (settings for the stage's selection) and an
 * `actions` bar (save state, conflict, zoom, the app's action descriptors) registered with useStickyBars. `#navigator-actions` (a "+ New" button) renders first in the navigator, before its filter and list, at every width.
 *
 * xl and up: three columns on Reka's Splitter, resizable (keyboard in 5-point steps) and collapsible. lg: navigator and stage, the inspector in a right drawer.
 * Below lg: tabs (Navigator / Stage / Inspector, and the footer's when there is one). Pane sizes, collapsed panes, the zoom and the phone tab are remembered per person
 * and `workbench-key` through `layout` (the server's WorkbenchLayoutData: state, version and the kit's preference endpoint; guarded localStorage without it).
 *
 * A collapsed navigator, inspector or footer keeps a slim bar with its label and Expand button (WorkbenchCollapsedBar).
 * Keys: ↑/↓ or j/k in the navigator, Alt+↑/↓ to move the current item, Shift+F10 or the context-menu key for the current item's menu (its `actions`), ⌘[ / ⌘] to collapse the navigator / inspector, `/` for the navigator filter, Esc closes the
 * drawer, else clears the stage elements, else the selection, F6 / Shift+F6 cycle the regions, ⌘+ / ⌘- / ⌘0 zoom. Each region is a labelled landmark. State (selection, inspector, zoom) is useWorkbench(key)'s.
 *
 * With no items yet (useWorkbench().isEmpty) the stage renders the host's `#empty` slot (`{ actions, act, label }`) in place of `#stage`, only when one is given;
 * without `#empty` it renders `#stage` as usual. `#navigator-item` (`{ item, selected, index }`) renders each navigator row's content in every layout, keeping the
 * kit's navigator: its role="option" rows, keys, filter and row menus.
 * `navigator-layout="filmstrip"` lists the items as a slides app's left rail (number, a 16:9 thumbnail from each item's `thumbnail`, label); `list` is the default.
 * `#navigator-thumbnail` (`{ item, index, selected }`, filmstrip only) fills each thumbnail box with the host's own render, inert and aria-hidden.
 *
 * It fills the viewport below the shell (100dvh less 8rem, at least 28rem), so the panes and the stage have a height to resize and fit in; `fill` false leaves it
 * at its content's height, for a Workbench inside a box of a definite height.
 *
 * `:form` makes it a form frame: one <form novalidate> around the grid, with Save as the actions bar's primary (type=submit; the app's actions go secondary)
 * and ⌘S / Ctrl+S. `form` is either an Inertia useForm() with `save` (the action descriptor the kit submits to: `form.submit(save.method, save.url)`), or a
 * useSubmitForm() result (no `save`; the kit calls `form.submit()`). `@submit` replaces the kit's submit with the app's own. Processing is Saving… with Save
 * disabled; a success is Saved; errors show a summary that focuses the first invalid field (revealing a collapsed inspector or another phone tab first); a
 * 409 conflict (a useSubmitForm's, or a visit's errors[<code>]) is the conflict alert with the edits kept; while dirty, leaving asks first (`warn-on-leave` false, or `warnOnLeave: false` on a
 * useSubmitForm, turns that off). `conflict-alert` false drops the kit's conflict alert for a page that shows its own; the page's Keep my edits calls
 * `useWorkbench(key).keepMine(form)`, which clears the conflict, keeps the values and enables Save again. Kit Buttons inside stay type=button: only Save submits. At lg the inspector is a drawer outside the <form> (a dialog in the
 * top layer), so Enter there does not submit; Save and ⌘S do. Without `:form` nothing of this renders.
 * Save is disabled, and ⌘S and Enter are ignored, while the save cannot succeed (formFrame.ts's useSubmitGate): pending work in flight (an upload), an open
 * conflict, or `can-submit` false (a boolean, Ref or getter; or a useSubmitForm's `canSubmit` option); Save is described by the reason. After a failed save the
 * bar says "Not saved" until the next edit, then "Unsaved changes" again (editedSaveState).
 */
// The form the Workbench saves: a useForm() (submit(method, url, options)) or a useSubmitForm() (submit()). Methods, so a typed form of either kind is accepted.
interface WorkbenchForm extends FormLike {
    errors?: Record<string, string | undefined>;
    submit(...args: any[]): unknown;
}
const props = withDefaults(
    defineProps<{
        workbenchKey: string;
        label: string;
        items?: WorkbenchItem[];
        current?: string | null;
        multiple?: boolean;
        labels?: Partial<WorkbenchLabels>;
        layout?: {
            state: LayoutState | null;
            preference: LayoutPreference | null;
        } | null;
        reorder?: ReorderAction | null;
        version?: string | number | null;
        actions?: ActionDescriptor[];
        zoom?: boolean;
        canvas?: { width: number; height: number } | null;
        filterable?: boolean;
        fill?: boolean;
        elementAttribute?: string | null;
        keys?: boolean;
        defaultCollapsed?: { navigator?: boolean; inspector?: boolean } | null;
        actionsBar?: boolean;
        headings?: boolean;
        navigatorMode?: 'push' | 'overlay';
        navigatorLayout?: WorkbenchNavigatorLayout;
        closeOnSelect?: boolean;
        saveStatus?: 'kit' | 'none';
        conflictAlert?: boolean;
        form?: WorkbenchForm | null;
        save?: ActionDescriptor | null;
        saveLabel?: string;
        errorsLabel?: string;
        warnOnLeave?: boolean;
        onSubmit?: (() => unknown) | null;
        canSubmit?: MaybeRefOrGetter<boolean>;
    }>(),
    {
        canSubmit: true,
        form: null,
        save: null,
        saveLabel: 'Save',
        errorsLabel: 'There are errors in your submission.',
        warnOnLeave: true,
        onSubmit: null,
        saveStatus: 'kit',
        conflictAlert: true,
        elementAttribute: null,
        keys: true,
        defaultCollapsed: null,
        actionsBar: true,
        headings: true,
        navigatorMode: 'push',
        navigatorLayout: 'list',
        closeOnSelect: false,
        items: () => [],
        current: null,
        multiple: true,
        labels: () => ({}),
        layout: null,
        reorder: null,
        version: null,
        actions: () => [],
        zoom: false,
        canvas: null,
        filterable: true,
        fill: true,
    },
);
// The template's root is the optional <form> (FormFrame), so the host's attributes (class, data-*) go on the grid by hand, where they always went.
defineOptions({ inheritAttrs: false });
// `action` carries the item when it came from a navigator item's menu, and no item from the actions bar (or the empty state's `act`).
const emit = defineEmits<{
    action: [action: ActionDescriptor, item?: WorkbenchItem];
}>();
const slots = defineSlots<{
    navigator?(): unknown;
    'navigator-actions'?(): unknown;
    toolbar?(): unknown;
    stage?(): unknown;
    footer?(): unknown;
    inspector?(): unknown;
    status?(): unknown;
    actions?(): unknown;
    /** No items yet: the stage's empty state, with the Workbench's action descriptors and `act` to run one through the `action` event. */
    empty?(props: WorkbenchEmptySlotProps): unknown;
    /** A navigator row's content, inside the kit's role="option" wrapper (the listbox semantics, keys and row menus stay the kit's). No focusable element. */
    'navigator-item'?(props: WorkbenchNavigatorItemSlotProps): unknown;
    /** Filmstrip only: fills each row's 16:9 thumbnail box in place of the image or placeholder (a live render). Rendered inert and aria-hidden. */
    'navigator-thumbnail'?(
        props: WorkbenchNavigatorThumbnailSlotProps,
    ): unknown;
}>();
const wb = useWorkbench(props.workbenchKey);
const names = computed<WorkbenchLabels>(() => ({
    ...DEFAULT_LABELS,
    ...props.labels,
}));
const hasFooter = computed(() => !!slots.footer);
const hasNavigatorItem = computed(() => !!slots['navigator-item']);
const hasNavigatorThumbnail = computed(() => !!slots['navigator-thumbnail']);
const hasToolbar = computed(() => !!slots.toolbar);
// `#empty` (no items yet, wb.isEmpty): the host's empty state in the stage, with the Workbench's action descriptors and `act` to run one through the `action` event.
// Opt-in: the stage gives way to `#empty` only when the host provides one. A Workbench with no items and no `#empty` (a stage-only one, such as a
// presenter console with no navigator) renders its `#stage` exactly as before; the kit adds no default empty state.
const showEmpty = computed(() => wb.isEmpty && !!slots.empty);
const emptyProps = computed<WorkbenchEmptySlotProps>(() => ({
    actions: props.actions,
    act: (action: ActionDescriptor) => emit('action', action),
    label: names.value.stage,
}));

// The store follows the server: items, the reorder action and its version token, multi-select. The store lives per key across visits (pane prefs and zoom
// stay), but the selection is this page's: each mount starts from the server's `current` with no stage elements, and so does every change of `current`
// (a link, back, a visit that keeps this component).
wb.multiple = props.multiple;
wb.setItems(props.items);
// The attribute the stage's elements carry their id in, for the stage toolbar's selection rects; unset keeps the default (or a useWorkbench option).
watch(
    () => props.elementAttribute,
    (name) => {
        if (name) wb.setElementAttribute(name);
    },
    { immediate: true },
);
watch(
    () => props.items,
    (items) => wb.setItems(items),
);
watch(
    () => [props.reorder, props.version] as const,
    ([action, token]) => wb.setReorder(action, token),
    { immediate: true },
);
function arrive(current: string | null): void {
    wb.clearElements();
    if (current !== null) wb.select(current, 'replace');
    else wb.clear();
}
arrive(props.current);
watch(() => props.current, arrive);

const store = createLayoutStore({
    key: props.workbenchKey,
    state: props.layout?.state ?? null,
    preference: props.layout?.preference ?? null,
    changed: (layout) => {
        if (layout.zoom) wb.zoom = layout.zoom;
    },
});
wb.attachLayout(store);
const splitter = store.splitter();
if (
    store.layout.tab &&
    workbenchTabs(true).some((tab) => tab.value === store.layout.tab)
)
    wb.tab = store.layout.tab as WorkbenchRegion;
watch(
    () => wb.tab,
    (tab) => void store.update({ tab }),
);

// The mode follows the viewport (Tailwind's lg and xl), one live layout at a time.
let xl: MediaQueryList | undefined;
let lg: MediaQueryList | undefined;
const onMedia = () => {
    wb.mode = workbenchMode(xl?.matches ? 1280 : lg?.matches ? 1024 : 0);
    if (wb.mode !== 'drawer') wb.drawerOpen = false;
    applyDefaultCollapse();
};

const root = ref<HTMLElement>();
const navigator = ref<InstanceType<typeof WorkbenchNavigator>>();
const navigatorPanel = ref<InstanceType<typeof SplitterPanel>>();
const inspectorPanel = ref<InstanceType<typeof SplitterPanel>>();
// Each region's element id, for a collapsed pane's Expand button (`aria-controls`).
const uid = useId();
const regionId = (region: WorkbenchRegion) => `workbench-${uid}-${region}`;
const regionEl = (region: WorkbenchRegion) =>
    root.value?.querySelector<HTMLElement>(
        `[data-workbench-region="${region}"]`,
    ) ?? null;
const toggle = (
    panel: InstanceType<typeof SplitterPanel> | undefined,
    region: WorkbenchRegion,
) => {
    if (!panel) return;
    if (wb.collapsed[region]) panel.expand();
    else panel.collapse();
};

// navigator-mode="overlay" (the column layouts): the navigator is a sheet over the stage, never a column, so opening it never resizes the stage. The Modal gives the
// focus trap, Esc and focus back to the toggle; `close-on-select` closes it when an item is chosen. Below lg the tabs are unchanged.
const overlay = computed(
    () => props.navigatorMode === 'overlay' && wb.mode !== 'tabs',
);
const navigatorOpen = ref(false);
watch(
    () => wb.current,
    () => {
        if (overlay.value && props.closeOnSelect) navigatorOpen.value = false;
    },
);
// default-collapsed: each splitter group, the first time it shows, collapses the panes the host asks for unless the person has a remembered layout for it.
const defaulted = new Set<string>();
// `actions-bar` false drops the actions bar (its sticky row costs stage height on a dense surface); the host then drives the panes with useWorkbench().togglePane()
// and focusRegion(). With the headings off too, the bar was the panes' only toggle, so in development it warns if nothing has toggled a pane a few seconds in.
onMounted(() => {
    if (!import.meta.env?.DEV || props.actionsBar || props.headings) return;
    setTimeout(() => {
        if (wb.paneCalls === 0)
            console.warn(
                `<Workbench workbench-key="${props.workbenchKey}">: with headings and the actions bar off, the panes have no toggle of their own, and none has been toggled yet. Make sure your own controls call useWorkbench('${props.workbenchKey}').togglePane() or focusRegion() from your own controls.`,
            );
    }, 3000);
});
function applyDefaultCollapse(): void {
    const group =
        wb.mode === 'columns'
            ? 'columns'
            : wb.mode === 'drawer'
              ? 'columns-lg'
              : null;
    if (group === null || defaulted.has(group)) return;
    defaulted.add(group);
    const regions = (
        wb.mode === 'columns' ? ['navigator', 'inspector'] : ['navigator']
    ) as ('navigator' | 'inspector')[];
    void nextTick(() => {
        for (const region of defaultCollapses(
            props.defaultCollapsed,
            store.layout,
            group,
            regions,
        )) {
            if (region === 'navigator' && overlay.value) continue;
            (region === 'navigator'
                ? navigatorPanel.value
                : inspectorPanel.value
            )?.collapse();
        }
    });
}

function focusRegion(region: WorkbenchRegion): void {
    if (wb.mode === 'tabs') wb.tab = region;
    if (region === 'inspector' && wb.mode === 'drawer') wb.drawerOpen = true;
    if (region === 'navigator' && overlay.value) navigatorOpen.value = true;
    else if (region === 'navigator' && wb.collapsed.navigator)
        navigatorPanel.value?.expand();
    void nextTick(() => {
        if (region === 'navigator' && (wb.current !== null || wb.isEmpty)) {
            navigator.value?.focusCurrent();
            return;
        }
        const el =
            region === 'inspector' && wb.mode === 'drawer'
                ? document.querySelector<HTMLElement>(
                      '[data-workbench-region="inspector"]',
                  )
                : regionEl(region);
        el?.focus({ preventScroll: false });
    });
}
const activeRegion = (target: EventTarget | null): WorkbenchRegion | null =>
    ((target as Element | null)
        ?.closest?.('[data-workbench-region]')
        ?.getAttribute('data-workbench-region') as WorkbenchRegion | null) ??
    null;

function onKeydown(event: KeyboardEvent): void {
    const region = activeRegion(event.target);
    // `keys` false: a live session or transport owns the keyboard, so none of the Workbench's bindings fire (focus management and landmarks stay).
    const command = enabledCommand(props.keys, event, {
        inNavigator: region === 'navigator',
        typing: isTyping(event.target as HTMLElement | null),
        drawerOpen: wb.drawerOpen,
        elements: wb.elements.length > 0,
        selection: wb.selected.length > 0,
        save: hasForm.value,
    });
    if (!command) return;
    if (command === 'save') {
        requestSave(event);
        return;
    }
    event.preventDefault();
    switch (command) {
        case 'next':
            wb.next();
            navigator.value?.focusCurrent();
            break;
        case 'prev':
            wb.prev();
            navigator.value?.focusCurrent();
            break;
        case 'move-down':
            void wb.moveCurrent(1).then(() => navigator.value?.focusCurrent());
            break;
        case 'move-up':
            void wb.moveCurrent(-1).then(() => navigator.value?.focusCurrent());
            break;
        case 'collapse-navigator':
            wb.togglePane('navigator');
            break;
        case 'collapse-inspector':
            if (wb.mode === 'drawer') wb.drawerOpen = !wb.drawerOpen;
            else wb.togglePane('inspector');
            break;
        case 'focus-filter':
            focusRegion('navigator');
            void nextTick(() => navigator.value?.focusFilter());
            break;
        case 'close-drawer':
            wb.drawerOpen = false;
            break;
        case 'clear-elements':
            wb.clearElements();
            break;
        case 'clear-selection':
            wb.clear();
            break;
        case 'next-region':
        case 'prev-region': {
            const order = regionOrder(wb.mode, {
                footer: hasFooter.value,
                collapsed: overlay.value
                    ? { ...wb.collapsed, navigator: !navigatorOpen.value }
                    : wb.collapsed,
                drawerOpen: wb.drawerOpen,
            });
            const target = nextRegion(
                order,
                wb.mode === 'tabs' ? wb.tab : region,
                command === 'prev-region',
            );
            if (target) focusRegion(target);
            break;
        }
        case 'zoom-in':
            wb.zoomIn();
            break;
        case 'zoom-out':
            wb.zoomOut();
            break;
        case 'zoom-reset':
            wb.zoomReset();
            break;
    }
}

const cleanups: (() => void)[] = [];
onMounted(() => {
    xl = window.matchMedia('(min-width: 80rem)');
    lg = window.matchMedia('(min-width: 64rem)');
    onMedia();
    xl.addEventListener('change', onMedia);
    lg.addEventListener('change', onMedia);
    cleanups.push(
        wb.registerPane('navigator', () => {
            if (overlay.value) navigatorOpen.value = !navigatorOpen.value;
            else toggle(navigatorPanel.value, 'navigator');
        }),
        wb.registerPane('inspector', () =>
            toggle(inspectorPanel.value, 'inspector'),
        ),
    );
    for (const region of ['navigator', 'stage', 'inspector', 'footer'] as const)
        cleanups.push(wb.registerFocus(region, () => focusRegion(region)));
    // Keys that work wherever focus is on the page (zoom, F6, collapsing panes); the rest stay inside the Workbench.
    document.addEventListener('keydown', onDocumentKeydown);
});
onBeforeUnmount(() => {
    xl?.removeEventListener('change', onMedia);
    lg?.removeEventListener('change', onMedia);
    document.removeEventListener('keydown', onDocumentKeydown);
    cleanups.forEach((cleanup) => cleanup());
    wb.attachLayout(null);
});
function onDocumentKeydown(event: KeyboardEvent): void {
    if (event.defaultPrevented || root.value?.contains(event.target as Node))
        return;
    const overlayOpen = !!document.querySelector(
        'dialog[open]:not([data-workbench-drawer]), [role="dialog"]:not([data-workbench-drawer]), [role="menu"]',
    );
    const active = document.activeElement;
    if (
        documentEscape({
            key: event.key,
            typing:
                isTyping(event.target as HTMLElement | null) ||
                isTyping(active as HTMLElement | null),
            activeIsBody: active === document.body || active === null,
            activeInWorkbench: !!active && !!root.value?.contains(active),
            overlayOpen,
        })
    ) {
        onKeydown(event);
        return;
    }
    if (overlayOpen) return;
    if (
        event.key === 'F6' ||
        ((event.metaKey || event.ctrlKey) &&
            ['[', ']', '=', '+', '-', '_', '0', 's', 'S'].includes(event.key))
    )
        onKeydown(event);
}
const tabs = computed(() => workbenchTabs(hasFooter.value, names.value));

// ── `:form`: the form frame ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
const formId = `workbench-${uid}-form`;
const SAVE_BUSY = 'Saving…';
const formEl = (): HTMLFormElement | undefined =>
    root.value?.closest<HTMLFormElement>('form[data-workbench-form]') ??
    undefined;
const hasForm = computed(() => !!props.form);
if (
    props.form &&
    !isSubmitForm(props.form) &&
    !props.save &&
    !props.onSubmit &&
    import.meta.env?.DEV
) {
    console.error(
        `<Workbench workbench-key="${props.workbenchKey}">: a useForm() as :form needs \`save\` (the action descriptor to submit to), or pass a useSubmitForm() result.`,
    );
}
const frame = useFormFrameStatus(() => props.form ?? undefined, {
    labels: () => ({
        busyLabel: SAVE_BUSY,
        savedLabel: '',
        dirtyLabel: '',
        errorsLabel: props.errorsLabel,
        upToDateLabel: '',
    }),
});
const showSummary = computed(
    () =>
        hasForm.value &&
        props.saveStatus === 'kit' &&
        !!props.form?.hasErrors &&
        wb.saveState !== 'conflict',
);
const { askLeave, leave } = useLeaveGuard(
    () => hasForm.value && frame.dirty.value,
    () =>
        hasForm.value && props.warnOnLeave && props.form?.warnOnLeave !== false,
    formEl,
    () => hasForm.value && frame.processing.value,
);
// The bar's save state follows the form: Saving… while processing, Unsaved changes while dirty (wb.dirty), and the outcome of each submit.
watch(
    () => hasForm.value && frame.processing.value,
    (busy) => {
        if (busy) wb.markSaving();
    },
);
watch(
    () => hasForm.value && !!props.form?.isDirty,
    (dirty) => {
        if (dirty && !frame.processing.value && wb.saveState !== 'conflict')
            wb.markDirty();
    },
);
// A failed save stays "Not saved" only until the next edit (a form that was dirty before the failure stays dirty, so the watch above does not fire again).
// The data is a useSubmitForm's `data`, or a useForm's `data()`.
const formData = () => {
    const form = props.form as (WorkbenchForm & { data?: unknown }) | null;
    return typeof form?.data === 'function'
        ? (form.data as () => unknown).call(form)
        : form?.data;
};
watch(
    formData,
    () => {
        if (
            hasForm.value &&
            !frame.processing.value &&
            editedSaveState(wb.saveState, !!props.form?.isDirty) !==
                wb.saveState
        )
            wb.markDirty();
    },
    { deep: true },
);
// Why Save cannot succeed now: pending work, an open conflict (the form's, or a useForm's 409, read from its errors), or `can-submit` false.
const gate = useSubmitGate(
    () => props.form ?? undefined,
    () => props.canSubmit,
    `${formId}-blocked`,
    () => formConflict(props.form) !== null || wb.saveState === 'conflict',
);

// The first invalid field's pane is shown before it is focused: the phone tab it is on, an expanded inspector or navigator, or the lg drawer opened.
const formScopes = () => [
    root.value,
    document.querySelector(
        '[data-workbench-drawer] [data-workbench-region="inspector"]',
    ),
];
function revealFirstInvalid(): Promise<HTMLElement | null> {
    return focusFirstInvalid(formScopes, props.form?.errors, (field) => {
        if (!field) {
            if (wb.mode === 'drawer') wb.drawerOpen = true;
            else if (wb.mode === 'columns' && wb.collapsed.inspector)
                inspectorPanel.value?.expand();
            return;
        }
        const region = activeRegion(field);
        if (wb.mode === 'tabs' && region && wb.tab !== region) wb.tab = region;
        if (region === 'inspector' && wb.collapsed.inspector)
            inspectorPanel.value?.expand();
        if (region === 'navigator' && wb.collapsed.navigator && !overlay.value)
            navigatorPanel.value?.expand();
    });
}
function settle(ok: boolean, conflict: boolean): void {
    if (ok) wb.markSaved();
    else if (conflict) wb.markConflict();
    else {
        wb.markFailed();
        if (props.form?.hasErrors) void revealFirstInvalid();
    }
}
async function runSave(): Promise<void> {
    const form = props.form;
    if (!form || form.processing || gate.blocked.value) return;
    if (props.onSubmit) {
        await props.onSubmit();
        return;
    }
    if (isSubmitForm(form)) {
        const ok = await form.submit();
        settle(ok, form.conflict);
        return;
    }
    if (!props.save) return;
    // An Inertia visit: a validation failure and a conflict (a 409, answered as errors[<code>]) both arrive at onError; the edits are kept.
    form.submit(props.save.method.toLowerCase() as Method, props.save.url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => settle(true, false),
        onError: () =>
            void nextTick(() => settle(false, formConflict(form) !== null)),
    });
}
// ⌘S / Ctrl+S: requestSubmit on the form, so it goes the way Enter and Save do. Ignored while processing or blocked, while another modal is open, and when the focused
// element (a contenteditable that binds Mod+S itself) has already handled it (defaultPrevented).
function requestSave(event: KeyboardEvent): void {
    const form = formEl();
    if (event.defaultPrevented || !form) return;
    event.preventDefault();
    if (
        frame.processing.value ||
        gate.blocked.value ||
        document.querySelector(
            'dialog[open]:not([data-workbench-drawer]), [role="dialog"][aria-modal="true"]:not([data-workbench-drawer])',
        )
    )
        return;
    form.requestSubmit(
        form.querySelector<HTMLButtonElement>('[data-workbench-save]') ??
            undefined,
    );
}
// One <form> around the grid only with `:form`; without it the children render as they always have (no wrapper element).
const FormFrame: FunctionalComponent<{ enabled: boolean }> = (
    frameProps,
    { slots },
) =>
    frameProps.enabled
        ? h(
              'form',
              {
                  id: formId,
                  novalidate: true,
                  class: 'contents',
                  'data-workbench-form': '',
                  onSubmit: (event: Event) => {
                      event.preventDefault();
                      void runSave();
                  },
              },
              slots.default?.(),
          )
        : slots.default?.();
FormFrame.props = ['enabled'];
// On a tinted page ground (README, "Tinted page grounds") the workbench is one card, unless it already sits on one; on a white ground nothing changes.
const surface = useGroundSurface();
</script>
<template>
    <FormFrame :enabled="hasForm">
        <div
            ref="root"
            v-bind="$attrs"
            role="group"
            :aria-label="label"
            :class="[
                'grid min-h-0 min-w-0 grid-rows-[minmax(0,1fr)_auto] gap-2',
                fill && 'h-[calc(100dvh-8rem)] min-h-[28rem]',
                surface && `${groundCard} p-2`,
            ]"
            :data-ground-surface="surface || undefined"
            :data-workbench="workbenchKey"
            :data-mode="wb.mode"
            @keydown="onKeydown"
        >
            <!-- xl and up: three columns. -->
            <SplitterGroup
                v-if="wb.mode === 'columns'"
                direction="horizontal"
                auto-save-id="columns"
                :storage="splitter"
                :keyboard-resize-by="5"
                class="min-h-0 min-w-0"
                data-workbench-columns
            >
                <template v-if="!overlay">
                    <SplitterPanel
                        id="navigator"
                        ref="navigatorPanel"
                        :default-size="20"
                        :min-size="12"
                        :max-size="35"
                        collapsible
                        :collapsed-size="0"
                        class="min-h-0 min-w-0"
                        @collapse="wb.collapsed.navigator = true"
                        @expand="wb.collapsed.navigator = false"
                    >
                        <nav
                            :id="regionId('navigator')"
                            :aria-label="names.navigator"
                            tabindex="-1"
                            class="flex h-full min-h-0 flex-col gap-1 focus-visible:outline-none"
                            data-workbench-region="navigator"
                        >
                            <div
                                v-if="headings"
                                class="flex shrink-0 items-center justify-between gap-2"
                            >
                                <h2
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    {{ names.navigator }}
                                </h2>
                                <PaneToggle
                                    :label="names.navigator"
                                    :collapsed="false"
                                    @click="wb.togglePane('navigator')"
                                />
                            </div>
                            <div
                                v-if="$slots['navigator-actions']"
                                class="flex shrink-0 flex-wrap items-center gap-2"
                                data-workbench-navigator-actions
                            >
                                <slot name="navigator-actions" />
                            </div>
                            <slot name="navigator"
                                ><WorkbenchNavigator
                                    ref="navigator"
                                    :workbench-key="workbenchKey"
                                    :label="names.navigator"
                                    :filterable="filterable"
                                    :reorderable="!!reorder"
                                    :layout="navigatorLayout"
                                    @action="
                                        (action, item) =>
                                            emit('action', action, item)
                                    "
                                    ><template
                                        v-if="hasNavigatorItem"
                                        #item="{ item, selected, index }"
                                        ><slot
                                            name="navigator-item"
                                            :item="item"
                                            :selected="selected"
                                            :index="index" /></template
                                    ><template
                                        v-if="hasNavigatorThumbnail"
                                        #thumbnail="{ item, index, selected }"
                                        ><slot
                                            name="navigator-thumbnail"
                                            :item="item"
                                            :index="index"
                                            :selected="
                                                selected
                                            " /></template></WorkbenchNavigator
                            ></slot>
                        </nav>
                    </SplitterPanel>
                    <WorkbenchCollapsedBar
                        v-if="wb.collapsed.navigator"
                        :label="names.navigator"
                        :controls="regionId('navigator')"
                        edge="side"
                        @expand="wb.togglePane('navigator')"
                    />
                    <SplitterResizeHandle
                        :aria-label="`Resize ${names.navigator.toLowerCase()} pane`"
                        class="focus-visible:bg-accent focus-visible:ring-ring w-3 shrink-0 rounded focus-visible:ring-2 focus-visible:outline-none"
                        ><span
                            aria-hidden="true"
                            class="bg-border mx-auto block h-full w-px"
                    /></SplitterResizeHandle>
                </template>
                <SplitterPanel
                    id="stage"
                    :min-size="30"
                    class="min-h-0 min-w-0"
                >
                    <section
                        :aria-label="names.stage"
                        tabindex="-1"
                        class="h-full min-h-0 min-w-0 focus-visible:outline-none"
                        data-workbench-region="stage"
                    >
                        <WorkbenchStage
                            :workbench-key="workbenchKey"
                            :storage="splitter"
                            :footer="hasFooter"
                            :toolbar="hasToolbar"
                            :canvas="canvas"
                            :stage-label="names.stage"
                            :footer-label="names.footer"
                        >
                            <template v-if="hasToolbar" #toolbar
                                ><slot name="toolbar"
                            /></template>
                            <template #stage
                                ><slot
                                    v-if="showEmpty"
                                    name="empty"
                                    v-bind="emptyProps" /><slot
                                    v-else
                                    name="stage"
                            /></template>
                            <template v-if="hasFooter" #footer
                                ><slot name="footer"
                            /></template>
                        </WorkbenchStage>
                    </section>
                </SplitterPanel>
                <SplitterResizeHandle
                    :aria-label="`Resize ${names.inspector.toLowerCase()} pane`"
                    class="focus-visible:bg-accent focus-visible:ring-ring w-3 shrink-0 rounded focus-visible:ring-2 focus-visible:outline-none"
                    ><span
                        aria-hidden="true"
                        class="bg-border mx-auto block h-full w-px"
                /></SplitterResizeHandle>
                <SplitterPanel
                    id="inspector"
                    ref="inspectorPanel"
                    :default-size="26"
                    :min-size="16"
                    :max-size="45"
                    collapsible
                    :collapsed-size="0"
                    class="min-h-0 min-w-0"
                    @collapse="wb.collapsed.inspector = true"
                    @expand="wb.collapsed.inspector = false"
                >
                    <aside
                        :id="regionId('inspector')"
                        :aria-label="names.inspector"
                        tabindex="-1"
                        class="flex h-full min-h-0 flex-col gap-1 focus-visible:outline-none"
                        data-workbench-region="inspector"
                    >
                        <div
                            v-if="headings"
                            class="flex shrink-0 items-center justify-between gap-2"
                        >
                            <h2
                                class="text-muted-foreground text-xs font-medium"
                            >
                                {{ names.inspector }}
                            </h2>
                            <PaneToggle
                                :label="names.inspector"
                                :collapsed="false"
                                @click="wb.togglePane('inspector')"
                            />
                        </div>
                        <PaneScroll class="min-h-0 flex-1"
                            ><slot name="inspector"
                                ><WorkbenchInspector
                                    :workbench-key="workbenchKey" /></slot
                        ></PaneScroll>
                    </aside>
                </SplitterPanel>
                <WorkbenchCollapsedBar
                    v-if="wb.collapsed.inspector"
                    :label="names.inspector"
                    :controls="regionId('inspector')"
                    edge="side"
                    @expand="wb.togglePane('inspector')"
                />
            </SplitterGroup>

            <!-- lg: navigator and stage; the inspector in a right drawer. -->
            <template v-else-if="wb.mode === 'drawer'">
                <SplitterGroup
                    direction="horizontal"
                    auto-save-id="columns-lg"
                    :storage="splitter"
                    :keyboard-resize-by="5"
                    class="min-h-0 min-w-0"
                    data-workbench-columns-lg
                >
                    <template v-if="!overlay">
                        <SplitterPanel
                            id="navigator"
                            ref="navigatorPanel"
                            :default-size="26"
                            :min-size="15"
                            :max-size="40"
                            collapsible
                            :collapsed-size="0"
                            class="min-h-0 min-w-0"
                            @collapse="wb.collapsed.navigator = true"
                            @expand="wb.collapsed.navigator = false"
                        >
                            <nav
                                :id="regionId('navigator')"
                                :aria-label="names.navigator"
                                tabindex="-1"
                                class="flex h-full min-h-0 flex-col gap-1 focus-visible:outline-none"
                                data-workbench-region="navigator"
                            >
                                <div
                                    v-if="headings"
                                    class="flex shrink-0 items-center justify-between gap-2"
                                >
                                    <h2
                                        class="text-muted-foreground text-xs font-medium"
                                    >
                                        {{ names.navigator }}
                                    </h2>
                                    <PaneToggle
                                        :label="names.navigator"
                                        :collapsed="false"
                                        @click="wb.togglePane('navigator')"
                                    />
                                </div>
                                <div
                                    v-if="$slots['navigator-actions']"
                                    class="flex shrink-0 flex-wrap items-center gap-2"
                                    data-workbench-navigator-actions
                                >
                                    <slot name="navigator-actions" />
                                </div>
                                <slot name="navigator"
                                    ><WorkbenchNavigator
                                        ref="navigator"
                                        :workbench-key="workbenchKey"
                                        :label="names.navigator"
                                        :filterable="filterable"
                                        :reorderable="!!reorder"
                                        :layout="navigatorLayout"
                                        @action="
                                            (action, item) =>
                                                emit('action', action, item)
                                        "
                                        ><template
                                            v-if="hasNavigatorItem"
                                            #item="{ item, selected, index }"
                                            ><slot
                                                name="navigator-item"
                                                :item="item"
                                                :selected="selected"
                                                :index="index" /></template
                                        ><template
                                            v-if="hasNavigatorThumbnail"
                                            #thumbnail="{
                                                item,
                                                index,
                                                selected,
                                            }"
                                            ><slot
                                                name="navigator-thumbnail"
                                                :item="item"
                                                :index="index"
                                                :selected="
                                                    selected
                                                " /></template></WorkbenchNavigator
                                ></slot>
                            </nav>
                        </SplitterPanel>
                        <WorkbenchCollapsedBar
                            v-if="wb.collapsed.navigator"
                            :label="names.navigator"
                            :controls="regionId('navigator')"
                            edge="side"
                            @expand="wb.togglePane('navigator')"
                        />
                        <SplitterResizeHandle
                            :aria-label="`Resize ${names.navigator.toLowerCase()} pane`"
                            class="focus-visible:bg-accent focus-visible:ring-ring w-3 shrink-0 rounded focus-visible:ring-2 focus-visible:outline-none"
                            ><span
                                aria-hidden="true"
                                class="bg-border mx-auto block h-full w-px"
                        /></SplitterResizeHandle>
                    </template>
                    <SplitterPanel
                        id="stage"
                        :min-size="40"
                        class="min-h-0 min-w-0"
                    >
                        <section
                            :aria-label="names.stage"
                            tabindex="-1"
                            class="h-full min-h-0 min-w-0 focus-visible:outline-none"
                            data-workbench-region="stage"
                        >
                            <WorkbenchStage
                                :workbench-key="workbenchKey"
                                :storage="splitter"
                                :footer="hasFooter"
                                :toolbar="hasToolbar"
                                :canvas="canvas"
                                :stage-label="names.stage"
                                :footer-label="names.footer"
                            >
                                <template v-if="hasToolbar" #toolbar
                                    ><slot name="toolbar"
                                /></template>
                                <template #stage
                                    ><slot
                                        v-if="showEmpty"
                                        name="empty"
                                        v-bind="emptyProps" /><slot
                                        v-else
                                        name="stage"
                                /></template>
                                <template v-if="hasFooter" #footer
                                    ><slot name="footer"
                                /></template>
                            </WorkbenchStage>
                        </section>
                    </SplitterPanel>
                </SplitterGroup>
                <Modal
                    v-model:open="wb.drawerOpen"
                    :title="names.inspector"
                    placement="drawer"
                    size="md"
                    data-workbench-drawer
                >
                    <aside
                        :aria-label="names.inspector"
                        tabindex="-1"
                        class="focus-visible:outline-none"
                        data-workbench-region="inspector"
                    >
                        <slot name="inspector"
                            ><WorkbenchInspector :workbench-key="workbenchKey"
                        /></slot>
                    </aside>
                </Modal>
            </template>

            <!-- Below lg: one region at a time, behind tabs. -->
            <div
                v-else
                class="grid min-h-0 min-w-0 grid-rows-[auto_minmax(0,1fr)] gap-2"
                data-workbench-tabs
            >
                <PaneTabs
                    :model-value="wb.tab"
                    :tabs="tabs"
                    :label="label"
                    @update:model-value="
                        (value) => (wb.tab = value as WorkbenchRegion)
                    "
                />
                <nav
                    v-show="wb.tab === 'navigator'"
                    :aria-label="names.navigator"
                    tabindex="-1"
                    class="flex min-h-0 flex-col gap-1 focus-visible:outline-none"
                    data-workbench-region="navigator"
                >
                    <div
                        v-if="$slots['navigator-actions']"
                        class="flex shrink-0 flex-wrap items-center gap-2"
                        data-workbench-navigator-actions
                    >
                        <slot name="navigator-actions" />
                    </div>
                    <slot name="navigator"
                        ><WorkbenchNavigator
                            ref="navigator"
                            :workbench-key="workbenchKey"
                            :label="names.navigator"
                            :filterable="filterable"
                            :reorderable="!!reorder"
                            :layout="navigatorLayout"
                            @action="
                                (action, item) => emit('action', action, item)
                            "
                            ><template
                                v-if="hasNavigatorItem"
                                #item="{ item, selected, index }"
                                ><slot
                                    name="navigator-item"
                                    :item="item"
                                    :selected="selected"
                                    :index="index" /></template
                            ><template
                                v-if="hasNavigatorThumbnail"
                                #thumbnail="{ item, index, selected }"
                                ><slot
                                    name="navigator-thumbnail"
                                    :item="item"
                                    :index="index"
                                    :selected="
                                        selected
                                    " /></template></WorkbenchNavigator
                    ></slot>
                </nav>
                <section
                    v-show="wb.tab === 'stage'"
                    :aria-label="names.stage"
                    tabindex="-1"
                    class="min-h-0 focus-visible:outline-none"
                    data-workbench-region="stage"
                >
                    <WorkbenchStage
                        :workbench-key="workbenchKey"
                        :toolbar="hasToolbar"
                        :canvas="canvas"
                        :stage-label="names.stage"
                        :footer-label="names.footer"
                    >
                        <template v-if="hasToolbar" #toolbar
                            ><slot name="toolbar"
                        /></template>
                        <template #stage
                            ><slot
                                v-if="showEmpty"
                                name="empty"
                                v-bind="emptyProps" /><slot v-else name="stage"
                        /></template>
                    </WorkbenchStage>
                </section>
                <!-- The inspector and footer tabs scroll inside their own row, as their panes do at lg and up, so their end clears the actions bar. -->
                <aside
                    v-show="wb.tab === 'inspector'"
                    :aria-label="names.inspector"
                    tabindex="-1"
                    class="flex min-h-0 flex-col focus-visible:outline-none"
                    data-workbench-region="inspector"
                >
                    <PaneScroll class="min-h-0 flex-1"
                        ><slot name="inspector"
                            ><WorkbenchInspector
                                :workbench-key="workbenchKey" /></slot
                    ></PaneScroll>
                </aside>
                <section
                    v-if="hasFooter"
                    v-show="wb.tab === 'footer'"
                    :aria-label="names.footer"
                    tabindex="-1"
                    class="flex min-h-0 flex-col focus-visible:outline-none"
                    data-workbench-region="footer"
                >
                    <PaneScroll class="min-h-0 flex-1"
                        ><slot name="footer"
                    /></PaneScroll>
                </section>
            </div>

            <Modal
                v-if="overlay"
                v-model:open="navigatorOpen"
                :title="names.navigator"
                placement="slideover"
                size="md"
                data-workbench-drawer
                data-workbench-navigator-overlay
            >
                <nav
                    :id="regionId('navigator')"
                    :aria-label="names.navigator"
                    tabindex="-1"
                    class="flex flex-col gap-1 focus-visible:outline-none"
                    data-workbench-region="navigator"
                >
                    <div
                        v-if="$slots['navigator-actions']"
                        class="flex shrink-0 flex-wrap items-center gap-2"
                        data-workbench-navigator-actions
                    >
                        <slot name="navigator-actions" />
                    </div>
                    <slot name="navigator"
                        ><WorkbenchNavigator
                            ref="navigator"
                            :workbench-key="workbenchKey"
                            :label="names.navigator"
                            :filterable="filterable"
                            :reorderable="!!reorder"
                            :layout="navigatorLayout"
                            @action="
                                (action, item) => emit('action', action, item)
                            "
                            ><template
                                v-if="hasNavigatorItem"
                                #item="{ item, selected, index }"
                                ><slot
                                    name="navigator-item"
                                    :item="item"
                                    :selected="selected"
                                    :index="index" /></template
                            ><template
                                v-if="hasNavigatorThumbnail"
                                #thumbnail="{ item, index, selected }"
                                ><slot
                                    name="navigator-thumbnail"
                                    :item="item"
                                    :index="index"
                                    :selected="
                                        selected
                                    " /></template></WorkbenchNavigator
                    ></slot>
                </nav>
            </Modal>

            <WorkbenchActions
                v-if="actionsBar"
                :pinned="fill"
                :workbench-key="workbenchKey"
                :actions="actions"
                :zoom="zoom"
                :save-status="saveStatus"
                :conflict-alert="conflictAlert"
                :inspector-label="names.inspector"
                :primary="!hasForm"
                @action="emit('action', $event)"
            >
                <template v-if="$slots.status" #status
                    ><slot name="status"
                /></template>
                <template v-if="showSummary" #summary>
                    <button
                        type="button"
                        class="text-destructive-text focus-visible:ring-ring min-w-0 text-sm font-medium underline underline-offset-2 hover:no-underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                        data-workbench-form-summary
                        @click="revealFirstInvalid"
                    >
                        {{ form?.errors?._form || errorsLabel }}
                    </button>
                </template>
                <template v-if="hasForm" #save>
                    <Button
                        type="submit"
                        :form="formId"
                        variant="primary"
                        :disabled="
                            frame.processing.value || !!gate.blocked.value
                        "
                        :aria-busy="frame.processing.value || undefined"
                        :aria-describedby="gate.describedBy.value"
                        :title="gate.reason.value ?? undefined"
                        data-workbench-save
                        :data-action="save?.key ?? 'save'"
                        :data-blocked="gate.blocked.value ?? undefined"
                        >{{
                            frame.processing.value ? SAVE_BUSY : saveLabel
                        }}</Button
                    >
                    <span
                        v-if="gate.reason.value"
                        :id="gate.describedBy.value"
                        class="sr-only"
                        data-workbench-save-blocked
                        >{{ gate.reason.value }}</span
                    >
                </template>
                <!-- Without the pane headings, or with an overlay navigator, the pane toggles live here, so every pane stays reachable by keyboard. -->
                <template v-if="wb.mode !== 'tabs' && (!headings || overlay)">
                    <PaneToggle
                        :label="names.navigator"
                        :collapsed="
                            overlay ? !navigatorOpen : wb.collapsed.navigator
                        "
                        data-workbench-pane-toggle="navigator"
                        @click="wb.togglePane('navigator')"
                    />
                    <PaneToggle
                        v-if="wb.mode === 'columns' && !headings"
                        :label="names.inspector"
                        :collapsed="wb.collapsed.inspector"
                        data-workbench-pane-toggle="inspector"
                        @click="wb.togglePane('inspector')"
                    />
                </template>
                <slot name="actions" />
            </WorkbenchActions>
            <Dialog
                v-if="hasForm"
                v-model:open="askLeave"
                title="Leave this page?"
                description="You have unsaved changes. If you leave, they are lost."
                confirm-label="Leave"
                cancel-label="Stay"
                @confirm="leave"
            />
        </div>
    </FormFrame>
</template>
