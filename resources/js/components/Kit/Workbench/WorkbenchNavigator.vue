<script setup lang="ts">
import { EllipsisVertical } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import ActionMenuItems from '@/components/Kit/Action/ActionMenuItems.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import {
    itemActions,
    itemMenuLabel,
    moveItem,
    opensItemMenu,
    type WorkbenchItem,
    type WorkbenchNavigatorLayout,
    type WorkbenchNavigatorThumbnailSlotProps,
} from './workbench';
import { useWorkbench } from './useWorkbench';

/*
 * The navigator's list: a listbox of the workbench's items (multi-selectable when the workbench is), with an optional filter that `/` focuses (Enter in it
 * never submits the Workbench's form). ↑/↓ and j/k move the
 * current item (the Workbench handles the keys and calls `focusCurrent`); click selects, Shift extends, ⌘/Ctrl toggles. With `reorderable`, items drag to a new
 * place and Alt+↑/↓ moves the current one; the new order goes through the app's reorder action and rolls back on a conflict. Reorder is off while a filter is on.
 * The `item` slot renders an item's content (`{ item, index, selected, current }`) inside the kit's role="option" row; it must hold no focusable element.
 *
 * Item menu: an item with `actions` (kit action descriptors) gets a row menu (Move up, Duplicate, Delete… as the host supplies them). Its button sits OUTSIDE the
 * listbox, in a sibling column that shares the rows through CSS subgrid, because a listbox may own only options: no focusable element is anywhere inside it. The
 * button shows on hover and focus (always on a coarse pointer), is a Tab stop for the current item only, and is named "Actions for <label>". Shift+F10, the
 * context-menu key or a right-click on the item opens the same menu, anchored to the row; Escape gives focus back to where the menu opened from. A chosen action
 * is emitted as `action` with its item (a GET link or a dialog navigates, as in every kit menu). An item without actions keeps the browser's own context menu.
 *
 * Thumbnails and `layout`: an item's `thumbnail` ({ src, alt }) renders as an <img> inside its option, before the `item` slot's content (the slot replaces the
 * label, not the picture). `list` (the default) shows it small beside the label; `filmstrip` stacks each row as a slides app's left rail: the item's number in the
 * whole order, a 16:9 box and the label under it. The box has a fixed aspect, so nothing moves when the image loads (lazily, decoded off the main thread), and an
 * item without a thumbnail gets an empty placeholder box in a filmstrip. The image is never draggable on its own: dragging it drags the row.
 * The `thumbnail` slot (`{ item, index, selected }`, filmstrip only) fills that box instead of the image or placeholder, such as a live scaled render of a slide.
 * Its wrapper is `inert` and aria-hidden, so nothing in it takes focus or is announced (the label stays the option's name), and the box's `contain: strict` and
 * overflow-hidden keep it from moving the layout.
 */
const props = withDefaults(
    defineProps<{
        workbenchKey: string;
        label: string;
        filterable?: boolean;
        reorderable?: boolean;
        filterLabel?: string;
        empty?: string;
        menuLabel?: string;
        layout?: WorkbenchNavigatorLayout;
    }>(),
    {
        filterable: true,
        reorderable: false,
        filterLabel: 'Filter',
        empty: 'Nothing here yet.',
        menuLabel: 'Actions for',
        layout: 'list',
    },
);
const emit = defineEmits<{
    action: [action: ActionDescriptor, item: WorkbenchItem];
}>();
defineSlots<{
    item?(props: {
        item: WorkbenchItem;
        index: number;
        selected: boolean;
        current: boolean;
    }): unknown;
    thumbnail?(props: WorkbenchNavigatorThumbnailSlotProps): unknown;
}>();
const wb = useWorkbench(props.workbenchKey);
const query = ref('');
const root = ref<HTMLElement>();
const list = ref<HTMLElement>();
const shown = computed<WorkbenchItem[]>(() => {
    const needle = query.value.trim().toLowerCase();
    return needle
        ? wb.items.filter((item) =>
              `${item.label} ${item.description ?? ''}`
                  .toLowerCase()
                  .includes(needle),
          )
        : wb.items;
});
const canReorder = computed(
    () => props.reorderable && query.value.trim() === '',
);
const dragging = ref<string | null>(null);
const optionId = (id: string) =>
    `wb-${props.workbenchKey}-${id}`.replace(/[^A-Za-z0-9_-]/g, '-');
const anyActions = computed(() =>
    shown.value.some((item) => itemActions(item).length > 0),
);
const filmstrip = computed(() => props.layout === 'filmstrip');

// The open item menu (one at a time), whether it opened from the item (keys or right-click: focus goes back to the item) or its button, and the hovered row.
const menuFor = ref<string | null>(null);
const menuFrom = ref<'item' | 'button'>('button');
const hovered = ref<string | null>(null);
const optionEl = (id: string) =>
    list.value?.querySelector<HTMLElement>(
        `[data-workbench-item="${CSS.escape(id)}"]`,
    ) ?? null;
function openMenu(item: WorkbenchItem, event: Event): void {
    if (!itemActions(item).length) return;
    event.preventDefault();
    menuFrom.value = 'item';
    menuFor.value = item.id;
}
function onItemKeydown(item: WorkbenchItem, event: KeyboardEvent): void {
    if (opensItemMenu(event)) openMenu(item, event);
}
function setMenu(item: WorkbenchItem, open: boolean): void {
    if (open) {
        menuFrom.value = 'button';
        menuFor.value = item.id;
    } else if (menuFor.value === item.id) menuFor.value = null;
}
// Opened from the item, the first action takes focus (as a keyboard-opened menu does), and closing gives focus back to the item, not to the button.
function onMenuOpenFocus(event: Event): void {
    if (menuFrom.value !== 'item') return;
    event.preventDefault();
    void nextTick(() =>
        document
            .querySelector<HTMLElement>(
                '[role=menu][data-state="open"] :is([role=menuitem], [role=menuitemcheckbox]):not([data-disabled])',
            )
            ?.focus(),
    );
}
function onMenuCloseFocus(item: WorkbenchItem, event: Event): void {
    if (menuFrom.value !== 'item') return;
    event.preventDefault();
    optionEl(item.id)?.focus();
}

// With nothing listed, focus lands on the empty message, so focusRegion('navigator') and ↑/↓ still have somewhere to go.
function focusCurrent(): void {
    void nextTick(() => {
        const id = wb.current;
        const el = !shown.value.length
            ? root.value?.querySelector<HTMLElement>('[data-workbench-empty]')
            : id === null
              ? null
              : optionEl(id);
        el?.focus({ preventScroll: false });
        el?.scrollIntoView?.({ block: 'nearest' });
    });
}
defineExpose({
    focusCurrent,
    focusFilter: () =>
        root.value
            ?.querySelector<HTMLInputElement>('[data-workbench-filter]')
            ?.focus(),
});

function drop(target: string): void {
    const from =
        dragging.value === null ? -1 : wb.order.indexOf(dragging.value);
    const to = wb.order.indexOf(target);
    dragging.value = null;
    if (from !== -1 && to !== -1 && from !== to)
        void wb.reorder(moveItem(wb.order, from, to));
}
</script>
<template>
    <div
        ref="root"
        class="flex h-full min-h-0 flex-col gap-2"
        data-workbench-navigator
    >
        <input
            v-if="filterable"
            v-model="query"
            type="search"
            :aria-label="filterLabel"
            :placeholder="`${filterLabel}…`"
            class="border-input bg-background focus-visible:ring-ring h-8 w-full shrink-0 rounded-md border px-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
            data-workbench-filter
            @keydown.enter.prevent
        />
        <!-- Nothing to list (no items, or none match the filter): a plain message, never an empty listbox (a listbox needs options, and an empty scroll box needs focus). -->
        <p
            v-if="!shown.length"
            tabindex="-1"
            class="text-muted-foreground m-0 px-2 py-3 text-sm focus-visible:outline-none"
            data-workbench-empty
        >
            {{ empty }}
        </p>
        <div
            v-else
            class="grid min-h-0 grid-cols-[minmax(0,1fr)_auto] grid-rows-[repeat(var(--kit-wb-rows),auto)] content-start gap-x-1 gap-y-0.5 overflow-auto"
            :style="{ '--kit-wb-rows': shown.length }"
            data-workbench-rows
        >
            <ul
                ref="list"
                role="listbox"
                :aria-label="label"
                :aria-multiselectable="wb.multiple || undefined"
                :aria-activedescendant="
                    wb.current ? optionId(wb.current) : undefined
                "
                :class="[
                    'col-start-1 row-span-(--kit-wb-rows) grid grid-rows-subgrid gap-0.5',
                    !anyActions && 'col-span-2',
                ]"
                data-workbench-list
            >
                <li
                    v-for="(item, index) in shown"
                    :id="optionId(item.id)"
                    :key="item.id"
                    role="option"
                    :aria-selected="wb.selected.includes(item.id)"
                    :aria-current="wb.current === item.id || undefined"
                    :tabindex="
                        wb.current === item.id ||
                        (wb.current === null && index === 0)
                            ? 0
                            : -1
                    "
                    :draggable="canReorder"
                    :class="[
                        'focus-visible:ring-ring flex cursor-default rounded-md text-sm focus-visible:ring-2 focus-visible:outline-none',
                        filmstrip
                            ? 'items-start gap-2 p-1.5'
                            : 'min-h-9 items-center gap-2 px-2',
                        wb.selected.includes(item.id)
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                        dragging === item.id && 'opacity-50',
                    ]"
                    :data-workbench-item="item.id"
                    @click="wb.select(item.id, $event)"
                    @keydown="onItemKeydown(item, $event)"
                    @contextmenu="openMenu(item, $event)"
                    @pointerenter="hovered = item.id"
                    @pointerleave="hovered = null"
                    @dragstart="dragging = item.id"
                    @dragend="dragging = null"
                    @dragover.prevent
                    @drop.prevent="drop(item.id)"
                >
                    <template v-if="filmstrip">
                        <!-- The number in the whole order (it stays put under a filter); the label names the option, so the number is not read out. -->
                        <span
                            class="w-5 shrink-0 pt-0.5 text-right text-xs tabular-nums"
                            aria-hidden="true"
                            data-workbench-number
                            >{{ wb.order.indexOf(item.id) + 1 }}</span
                        >
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span
                                class="border-border bg-muted relative block aspect-video w-full overflow-hidden rounded border contain-strict"
                                data-workbench-thumbnail-box
                            >
                                <span
                                    v-if="$slots.thumbnail"
                                    class="absolute inset-0 overflow-hidden"
                                    aria-hidden="true"
                                    inert
                                    data-workbench-thumbnail-slot
                                    ><slot
                                        name="thumbnail"
                                        :item="item"
                                        :index="index"
                                        :selected="
                                            wb.selected.includes(item.id)
                                        "
                                /></span>
                                <img
                                    v-else-if="item.thumbnail"
                                    :src="item.thumbnail.src"
                                    :alt="item.thumbnail.alt"
                                    loading="lazy"
                                    decoding="async"
                                    draggable="false"
                                    class="absolute inset-0 size-full object-cover"
                                    data-workbench-thumbnail
                                />
                            </span>
                            <span class="flex min-w-0 items-center gap-2"
                                ><slot
                                    name="item"
                                    :item="item"
                                    :index="index"
                                    :selected="wb.selected.includes(item.id)"
                                    :current="wb.current === item.id"
                                    ><span class="truncate">{{
                                        item.label
                                    }}</span></slot
                                ></span
                            >
                        </span>
                    </template>
                    <template v-else>
                        <span
                            v-if="item.thumbnail"
                            class="border-border bg-muted relative block aspect-video h-6 shrink-0 overflow-hidden rounded-sm border"
                            data-workbench-thumbnail-box
                        >
                            <img
                                :src="item.thumbnail.src"
                                :alt="item.thumbnail.alt"
                                loading="lazy"
                                decoding="async"
                                draggable="false"
                                class="absolute inset-0 size-full object-cover"
                                data-workbench-thumbnail
                            />
                        </span>
                        <slot
                            name="item"
                            :item="item"
                            :index="index"
                            :selected="wb.selected.includes(item.id)"
                            :current="wb.current === item.id"
                            ><span class="truncate">{{
                                item.label
                            }}</span></slot
                        >
                    </template>
                </li>
            </ul>
            <!-- The item menus: outside the listbox (it may own only options), one cell per row on the same subgrid rows. -->
            <div
                v-if="anyActions"
                class="col-start-2 row-span-(--kit-wb-rows) grid grid-rows-subgrid gap-0.5"
                data-workbench-item-menus
            >
                <div
                    v-for="item in shown"
                    :key="item.id"
                    :class="[
                        'flex',
                        filmstrip ? 'items-start pt-1.5' : 'items-center',
                    ]"
                    @pointerenter="hovered = item.id"
                    @pointerleave="hovered = null"
                >
                    <DropdownMenu
                        v-if="itemActions(item).length"
                        :open="menuFor === item.id"
                        :modal="false"
                        @update:open="(open: boolean) => setMenu(item, open)"
                    >
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                :aria-label="itemMenuLabel(item, menuLabel)"
                                :tabindex="
                                    wb.current === item.id ||
                                    (wb.current === null &&
                                        item.id === shown[0]?.id)
                                        ? 0
                                        : -1
                                "
                                :class="[
                                    'text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-7 items-center justify-center rounded-md focus-visible:opacity-100 focus-visible:ring-2 focus-visible:outline-none pointer-coarse:opacity-100',
                                    hovered === item.id || menuFor === item.id
                                        ? 'opacity-100'
                                        : 'opacity-0',
                                ]"
                                :data-workbench-item-menu="item.id"
                            >
                                <EllipsisVertical
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            @open-auto-focus="onMenuOpenFocus"
                            @close-auto-focus="
                                (event) => onMenuCloseFocus(item, event)
                            "
                        >
                            <ActionMenuItems
                                :actions="itemActions(item)"
                                :busy="wb.reordering"
                                :on-action="
                                    (action) => emit('action', action, item)
                                "
                            />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    </div>
</template>
