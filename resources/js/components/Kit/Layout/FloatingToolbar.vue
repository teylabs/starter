<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import HeaderActions from '@/components/Kit/Action/HeaderActions.vue';
import HeaderStatus from '@/components/Kit/Action/HeaderStatus.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { HeaderStatus as HeaderStatusValue } from '@/components/Kit/Action/headerSplit';
import {
    inverseControl,
    provideSurface,
} from '@/components/Kit/Surface/surface';
import { readOnlyLabel } from '@/components/Kit/Form/locked';
import { useLocked } from '@/components/Kit/Form/useLocked';
import { useWorkbench } from '@/components/Kit/Workbench/useWorkbench';
import {
    selectedRects,
    useWorkbenchStage,
} from '@/components/Kit/Workbench/workbenchStage';
import { useStickyBars } from './stickyBars';
import {
    deferToolbarAction,
    fitToolbarItems,
    runToolbarAction,
    sameToolbarMeasure,
    toolbarGroups,
    type ToolbarAction,
    type ToolbarFitMeasure,
} from './toolbarAction';
import { anchorToolbar, unionRect, type ToolbarAnchor } from './toolbarAnchor';

/*
 * A centred pill of page actions and status. `auto` (default) sits inline under short content, floats above the viewport's foot while long content scrolls past
 * and settles at its container's end (sticky); `float` stays fixed; `inline` stays in flow. `reveal` is a selector (the page header's actions): the slot's
 * `revealed` (also `v-model:revealed`) is true while that element is out of view, so the host can repeat those actions only when they cannot be reached. Actions
 * and status come in through the slot; nothing here reads a page or bench store (but the Workbench selection in `stage` mode). The slot is for ActionBar and STATUS only: every action is a server descriptor
 * rendered by ActionBar so it overflows into the ⌄ menu on phones. A button or link slotted directly cannot overflow (it would clip), so it warns in development. It provides the inverse tone and pill shape to the controls inside.
 * `items` are client-side actions (ToolbarAction: key, label, icon, disabled, reason; no URL) drawn after the slot; choosing one calls the host's `onAction`
 * (`@action`) from this component's own handler (runToolbarAction), never an emit from a part that may unmount. A disabled item stays focusable with its reason as a title.
 * Items keep the pill to one row: when they do not fit the width the pill has (measured on its items, a ResizeObserver, with hysteresis: fitToolbarItems), whole
 * trailing groups (`group`) move into a ⌄ "More actions" menu first, then trailing items, keeping the first N; a menu row reaches `onAction` after the menu has
 * closed (deferToolbarAction). Groups are separated by a rule, inline and in the menu. An `iconOnly` item shows its icon, with the label as its accessible name and
 * tooltip.
 * Inside a Workbench stage (the WorkbenchStage context): `stage` floats over the stage's selected elements (`wb.elements`, found by the Workbench's element attribute, `data-workbench-element`),
 * above them, below when there is no room, clamped inside the stage (anchorToolbar), and is invisible while nothing is selected. It is capped at the stage's
 * width less anchorToolbar's 8px insets, so a pill wider than the stage folds its items into ⌄ instead of running past the edge; `docked` renders in the stage
 * toolbar instead (in place when the Workbench has no toolbar). Both teleport into the stage, so the host may put the toolbar anywhere in its slots. `reposition()`
 * is exposed for a host that moves elements without changing the selection (a drag).
 * Floats at z-35: above the page's StickyBar (z-30) and under the modal layer (z-40, @inertiaui/modal's container) and sheets, so a modal opened from the pill's own slot covers it; registers its height with useStickyBars while it floats (so the shell's Toaster sits above it), honours the bottom safe-area inset, and needs ancestors that do not clip overflow. Attributes go on the pill.
 * Below `sm` the pill keeps one row (ActionBar, measuring its own width, collapses to its main action and a ⌄ menu when it does not fit, which keep their size while other content truncates); dark mode uses the popover surface with a border ring.
 * The page header's mirror: `actions` (server descriptors) and `status` ({ state, label }) render as the page header renders them (HeaderStatus, then HeaderActions,
 * split by headerSplit: a primary, up to two secondaries and More; only the primary below `sm`), after the slot. A non-navigating one goes to the host's
 * `onPageAction` (`@page-action`) when it has one (`onAction` stays the client items' handler), else is run with its confirmation.
 * Inside a locked LockedForm (the LockedForm spec) the pill shows the read-only label as status text after the slot, the reason or "View only", in the save action's place; the ActionBar inside hides edit actions itself.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        mode?: 'auto' | 'float' | 'inline' | 'stage' | 'docked';
        reveal?: string;
        label?: string;
        items?: ToolbarAction[];
        onAction?: (action: ToolbarAction) => void;
        actions?: ActionDescriptor[];
        status?: HeaderStatusValue | null;
        onPageAction?: (action: ActionDescriptor) => void;
    }>(),
    {
        mode: 'auto',
        reveal: undefined,
        label: 'Page actions',
        items: () => [],
        onAction: undefined,
        actions: () => [],
        status: null,
        onPageAction: undefined,
    },
);
// Handed on only when the host gives one, so HeaderActions runs the action itself otherwise. A prop like `onAction`, never an emit.
const pageListeners = computed(() =>
    props.onPageAction ? { onAction: props.onPageAction } : {},
);
const pill = useTemplateRef<HTMLElement>('pill');
useStickyBars().track(
    pill,
    () => props.mode === 'auto' || props.mode === 'float',
);
const revealed = defineModel<boolean>('revealed', { default: false });
const lock = useLocked();
provideSurface(() => ({ tone: 'inverse', shape: 'pill' }));
const icon = (item: ToolbarAction) => actionIcons[item.icon ?? ''];
const choose = (item: ToolbarAction) => runToolbarAction(props.onAction, item);
const itemClass = (item: ToolbarAction) => [
    inverseControl,
    'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-disabled:cursor-not-allowed aria-disabled:opacity-50',
    item.iconOnly ? 'w-9 justify-center' : 'px-3.5',
];
const chooseFromMenu = (item: ToolbarAction) =>
    deferToolbarAction(props.onAction, item);
const tip = (item: ToolbarAction) =>
    item.reason ?? (item.iconOnly ? item.label : undefined);

// Items overflow: the full row stays laid out (invisible and inert while overflowing) so its natural widths are measured, and the fit reads them against the
// width the items have. The ⌄ trigger is size-9 (36px).
const itemsRoot = useTemplateRef<HTMLElement>('itemsRoot');
const itemsFull = useTemplateRef<HTMLElement>('itemsFull');
const itemMeasure = ref<ToolbarFitMeasure | null>(null);
const wasOverflowing = ref(false);
const fit = computed(() =>
    fitToolbarItems(
        props.items,
        itemMeasure.value ?? { container: 0, widths: [] },
        wasOverflowing.value,
    ),
);
watch(
    () => fit.value.overflowing,
    (value) => {
        wasOverflowing.value = value;
    },
);
const inlineGroups = computed(() => toolbarGroups(fit.value.inline));
const menuGroups = computed(() => toolbarGroups(fit.value.menu));
const allGroups = computed(() => toolbarGroups(props.items));
let itemFrame = 0;
function measureItems(): void {
    itemFrame = 0;
    if (!itemsRoot.value || !itemsFull.value) return;
    const gap = parseFloat(getComputedStyle(itemsFull.value).columnGap) || 0;
    const children = [...itemsFull.value.children] as HTMLElement[];
    const rule = children.find((child) =>
        child.hasAttribute('data-toolbar-separator'),
    );
    const next = {
        container: itemsRoot.value.clientWidth,
        widths: children
            .filter((child) => child.hasAttribute('data-toolbar-action'))
            .map((child) => child.scrollWidth),
        gap,
        separator: rule ? rule.offsetWidth + gap : 0,
        trigger: 36,
    };
    if (!sameToolbarMeasure(itemMeasure.value, next)) itemMeasure.value = next;
}
// Never written in the observer's own callback (a ResizeObserver loop): one measure in the next frame.
function scheduleItems(): void {
    if (typeof requestAnimationFrame === 'undefined') return;
    if (itemFrame) cancelAnimationFrame(itemFrame);
    itemFrame = requestAnimationFrame(measureItems);
}
let itemObserver: ResizeObserver | undefined;
function observeItems(): void {
    itemObserver?.disconnect();
    if (typeof ResizeObserver === 'undefined' || !itemsRoot.value) return;
    itemObserver = new ResizeObserver(scheduleItems);
    itemObserver.observe(itemsRoot.value);
    if (itemsFull.value) itemObserver.observe(itemsFull.value);
    if (pill.value) itemObserver.observe(pill.value);
    measureItems();
}
watch([itemsRoot, () => props.items], observeItems, { flush: 'post' });

// Stage and docked modes: the Workbench stage this sits in, if any.
const stage = useWorkbenchStage();
const wb = stage ? useWorkbench(stage.workbenchKey) : null;
const anchored = computed(() => props.mode === 'stage' && !!stage);
const target = computed(
    () =>
        (props.mode === 'stage'
            ? stage?.viewport.value
            : props.mode === 'docked'
              ? stage?.toolbar.value
              : undefined) ?? null,
);
const position = ref<ToolbarAnchor | null>(null);
function reposition(): void {
    const viewport = stage?.viewport.value;
    if (!anchored.value || !viewport || !pill.value || !wb) {
        position.value = null;
        return;
    }
    const selection = unionRect(
        selectedRects(
            viewport,
            wb.elements.map((element) => element.id),
            wb.elementAttribute,
        ),
    );
    if (!selection) {
        position.value = null;
        return;
    }
    const box = viewport.getBoundingClientRect();
    const at = anchorToolbar(
        selection,
        {
            top: box.top + viewport.clientTop,
            left: box.left + viewport.clientLeft,
            width: viewport.clientWidth,
            height: viewport.clientHeight,
        },
        { width: pill.value.offsetWidth, height: pill.value.offsetHeight },
    );
    position.value = {
        ...at,
        top: at.top + viewport.scrollTop,
        left: at.left + viewport.scrollLeft,
    };
}
let frame = 0;
const schedule = () => {
    if (typeof requestAnimationFrame === 'undefined') return;
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(reposition);
};
watch(() => [wb?.elements, wb?.zoom, target.value, props.mode], schedule, {
    flush: 'post',
});
let resize: ResizeObserver | undefined;
let observedViewport: HTMLElement | undefined;
function observeStage(): void {
    resize?.disconnect();
    observedViewport?.removeEventListener('scroll', schedule);
    observedViewport = anchored.value ? stage?.viewport.value : undefined;
    if (!observedViewport) return;
    observedViewport.addEventListener('scroll', schedule, { passive: true });
    if (typeof ResizeObserver === 'undefined') return;
    resize = new ResizeObserver(schedule);
    resize.observe(observedViewport);
    if (pill.value) resize.observe(pill.value);
}
watch(() => [anchored.value, stage?.viewport.value], observeStage, {
    flush: 'post',
});
defineExpose({ reposition });

let observer: IntersectionObserver | undefined;
// Development guard: a control in the pill that ActionBar did not render is not supported (it cannot move into the phone menu).
function warnOnStrayControls(): void {
    if (!import.meta.env?.DEV) return;
    const stray = [
        ...(pill.value?.querySelectorAll('button, a[href], [role="button"]') ??
            []),
    ].filter(
        (el) =>
            !el.closest(
                '[data-action-bar], [data-header-actions], [data-toolbar-items]',
            ),
    );
    if (stray.length)
        console.warn(
            'FloatingToolbar: pass actions as descriptors to ActionBar (or client actions as `items`); its slot is status only. Found a control outside ActionBar:',
            stray[0],
        );
}
onMounted(() => {
    warnOnStrayControls();
    observeItems();
    observeStage();
    schedule();
    window.addEventListener('resize', schedule);
    const revealTarget = props.reveal
        ? document.querySelector(props.reveal)
        : null;
    if (!revealTarget || !('IntersectionObserver' in window)) return;
    observer = new IntersectionObserver(
        ([entry]) => {
            revealed.value = !entry.isIntersecting;
        },
        { threshold: 0 },
    );
    observer.observe(revealTarget);
});
onBeforeUnmount(() => {
    observer?.disconnect();
    resize?.disconnect();
    itemObserver?.disconnect();
    if (itemFrame && typeof cancelAnimationFrame !== 'undefined')
        cancelAnimationFrame(itemFrame);
    observedViewport?.removeEventListener('scroll', schedule);
    window.removeEventListener('resize', schedule);
    if (typeof cancelAnimationFrame !== 'undefined')
        cancelAnimationFrame(frame);
});
</script>
<template>
    <Teleport :to="target ?? 'body'" :disabled="!target">
        <div
            data-floating-toolbar
            :data-mode="mode"
            :data-placement="
                anchored ? (position?.placement ?? undefined) : undefined
            "
            :class="[
                'pointer-events-none flex justify-center',
                mode === 'docked' ? 'z-10 inline-flex px-0' : 'z-35 px-4',
                mode === 'float' &&
                    'fixed inset-x-0 bottom-[calc(1rem+env(safe-area-inset-bottom))]',
                mode === 'auto' &&
                    'sticky bottom-[calc(1rem+env(safe-area-inset-bottom))] mt-6',
                mode === 'inline' && 'mt-6',
                mode === 'stage' &&
                    'absolute top-(--kit-toolbar-top,0px) left-(--kit-toolbar-left,0px) z-20 max-w-[calc(100%-1rem)] px-0',
                mode === 'stage' && !position && 'invisible',
            ]"
            :style="
                anchored && position
                    ? {
                          '--kit-toolbar-top': `${position.top}px`,
                          '--kit-toolbar-left': `${position.left}px`,
                      }
                    : undefined
            "
        >
            <div
                ref="pill"
                v-bind="$attrs"
                role="toolbar"
                :aria-label="label"
                :class="['pointer-events-auto', items.length && 'flex-nowrap!']"
                class="bg-foreground/87 text-background supports-[not(backdrop-filter:blur(1px))]:bg-foreground [@media(prefers-reduced-transparency:reduce)]:bg-foreground dark:bg-popover/87 dark:text-popover-foreground dark:ring-border dark:supports-[not(backdrop-filter:blur(1px))]:bg-popover dark:[@media(prefers-reduced-transparency:reduce)]:bg-popover flex max-w-full flex-wrap items-center justify-center gap-x-3 gap-y-2 rounded-full px-3 py-2 text-sm shadow-lg ring-1 ring-white/15 backdrop-blur-md [--kit-outline-foreground:currentColor] [--kit-outline-muted:currentColor] [--kit-outline-wash:color-mix(in_oklab,currentColor_10%,transparent)] [--kit-outline:color-mix(in_oklab,currentColor_20%,transparent)] [--kit-paper:transparent] max-sm:flex-nowrap max-sm:*:min-w-0 max-sm:[&>:not(.shrink-0)]:shrink max-sm:[&>:not(.shrink-0)]:truncate [@media(prefers-reduced-transparency:reduce)]:backdrop-blur-none"
            >
                <slot :revealed="revealed" />
                <HeaderStatus v-if="status" :status="status" />
                <HeaderActions
                    v-if="actions.length"
                    :actions="actions"
                    v-bind="pageListeners"
                />
                <div
                    v-if="items.length"
                    ref="itemsRoot"
                    class="grid min-w-0 shrink"
                    data-toolbar-items
                    :data-overflowing="fit.overflowing || undefined"
                >
                    <div
                        ref="itemsFull"
                        role="group"
                        :class="[
                            'col-start-1 row-start-1 flex items-center gap-1',
                            fit.overflowing &&
                                'pointer-events-none invisible h-0 overflow-hidden',
                        ]"
                        :aria-hidden="fit.overflowing || undefined"
                        :inert="fit.overflowing || undefined"
                        data-toolbar-items-full
                    >
                        <template
                            v-for="(group, index) in allGroups"
                            :key="group[0].key"
                        >
                            <span
                                v-if="index"
                                class="mx-1 h-5 w-px shrink-0 bg-current opacity-25"
                                aria-hidden="true"
                                data-toolbar-separator
                            />
                            <button
                                v-for="item in group"
                                :key="item.key"
                                type="button"
                                :aria-label="
                                    item.iconOnly ? item.label : undefined
                                "
                                :aria-disabled="item.disabled || undefined"
                                :title="tip(item)"
                                :data-toolbar-action="item.key"
                                :data-group="item.group ?? undefined"
                                :class="itemClass(item)"
                                @click="choose(item)"
                            >
                                <component
                                    :is="icon(item)"
                                    v-if="icon(item)"
                                    class="size-4"
                                    aria-hidden="true"
                                /><span v-if="!item.iconOnly">{{
                                    item.label
                                }}</span>
                            </button>
                        </template>
                    </div>
                    <div
                        v-if="fit.overflowing"
                        role="group"
                        class="col-start-1 row-start-1 flex min-w-0 flex-nowrap items-center gap-1"
                        data-toolbar-items-fit
                    >
                        <template
                            v-for="(group, index) in inlineGroups"
                            :key="group[0].key"
                        >
                            <span
                                v-if="index"
                                class="mx-1 h-5 w-px shrink-0 bg-current opacity-25"
                                aria-hidden="true"
                                data-toolbar-separator
                            />
                            <button
                                v-for="item in group"
                                :key="item.key"
                                type="button"
                                :aria-label="
                                    item.iconOnly ? item.label : undefined
                                "
                                :aria-disabled="item.disabled || undefined"
                                :title="tip(item)"
                                :data-toolbar-action="item.key"
                                :data-group="item.group ?? undefined"
                                :class="itemClass(item)"
                                @click="choose(item)"
                            >
                                <component
                                    :is="icon(item)"
                                    v-if="icon(item)"
                                    class="size-4"
                                    aria-hidden="true"
                                /><span v-if="!item.iconOnly">{{
                                    item.label
                                }}</span>
                            </button>
                        </template>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    :class="[
                                        inverseControl,
                                        'focus-visible:outline-ring inline-flex size-9 shrink-0 items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-offset-2',
                                    ]"
                                    aria-label="More actions"
                                    title="More actions"
                                    data-toolbar-menu
                                >
                                    <ChevronDown
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                data-toolbar-menu-content
                            >
                                <template
                                    v-for="(group, index) in menuGroups"
                                    :key="group[0].key"
                                >
                                    <DropdownMenuSeparator v-if="index" />
                                    <DropdownMenuItem
                                        v-for="item in group"
                                        :key="item.key"
                                        :disabled="item.disabled"
                                        :title="item.reason ?? undefined"
                                        :data-toolbar-action="item.key"
                                        @select="chooseFromMenu(item)"
                                    >
                                        <component
                                            :is="icon(item)"
                                            v-if="icon(item)"
                                            class="size-4"
                                            aria-hidden="true"
                                        />{{ item.label }}
                                    </DropdownMenuItem>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
                <span
                    v-if="lock.locked"
                    class="text-sm"
                    data-floating-toolbar-readonly
                    >{{ readOnlyLabel(lock) }}</span
                >
            </div>
        </div>
    </Teleport>
</template>
