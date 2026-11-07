<script setup lang="ts" generic="T extends { id: string | number }">
import { ArrowDown, ArrowLeft, ArrowRight, ArrowUp } from '@lucide/vue';
import { computed, nextTick, ref, watch, type Ref } from 'vue';
import SortableHandle from './SortableHandle.vue';
import {
    createReorder,
    moveAnnouncement,
    moveItem,
    sendOrder,
    useSortable,
    type ReorderAction,
} from './sortable';

/**
 * A reorderable list: `ul`/`li` (a plain list, never a listbox), so its rows may hold links and buttons. Each row has a drag grip (pointer drag; ↑/↓, Home/End
 * while it is focused) and, unless `move-buttons` is false, visible Move up / Move down buttons, the non-drag way for touch and switch users. After a move a polite
 * live region says where the row went ("Slide 3 moved to position 2 of 8").
 *
 * The order is the host's. Without `reorder`, the list emits `update` with the new ids and the host applies them (v-model:items works too). With `reorder` (the
 * app's ReorderAction) and `version`, the list sends `{ order, version }` itself, optimistically, through the kit's shared transport: a 2xx emits `saved` with the
 * new version; a 409 or a stale code rolls the order back and calls `onConflict` (else emits `conflict`), and any other failure rolls back and emits `failed`.
 * `layout` lays the rows out as a `list` (default), a wrapping `grid` of tiles, or one scrolling `row`; in a grid or row the move buttons point back and forward.
 * `cutAfter` draws a labelled divider after that many items ("Shown on the listing" and the rest); it is not a row and moves nothing, the items do.
 * One reorder at a time; `disabled` turns drag, keys and the buttons off (a filter is on, a save is in flight).
 */
const props = withDefaults(
    defineProps<{
        items: T[];
        /** Names a row for the grip, the buttons and the announcement ("Slide 3"). */
        itemLabel: (item: T, index: number) => string;
        label: string;
        moveButtons?: boolean;
        disabled?: boolean;
        reorder?: ReorderAction | null;
        version?: string | number | null;
        onConflict?: ((order: string[]) => void) | null;
        layout?: 'list' | 'grid' | 'row';
        cutAfter?: number | null;
        cutLabel?: string;
    }>(),
    {
        layout: 'list',
        cutAfter: null,
        cutLabel: undefined,
        moveButtons: true,
        disabled: false,
        reorder: null,
        version: null,
        onConflict: null,
    },
);
const emit = defineEmits<{
    update: [ids: string[]];
    'update:items': [items: T[]];
    saved: [version: string | number | null];
    conflict: [order: string[]];
    failed: [];
}>();
defineSlots<{ default(props: { item: T; index: number }): unknown }>();

// The order shown: the host's items, or the optimistic order while a reorder is in flight (then back to the host's on a rollback).
const shown = ref([...props.items]) as Ref<T[]>;
watch(
    () => props.items,
    (items) => {
        shown.value = [...items];
    },
);
const ids = computed(() => shown.value.map((item) => String(item.id)));
const busy = ref(false);
let token: string | number | null = props.version;
watch(
    () => props.version,
    (version) => {
        token = version;
    },
);
const off = computed(() => props.disabled || busy.value);
const announcement = ref('');
const flow = computed(() => props.layout !== 'list');
const cutAt = computed(() =>
    props.cutAfter !== null &&
    props.cutAfter > 0 &&
    props.cutAfter < shown.value.length
        ? props.cutAfter
        : null,
);
const listClass = computed(
    () =>
        ({
            list: 'grid gap-1',
            grid: 'grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-3',
            row: 'flex gap-3 overflow-x-auto pb-2',
        })[props.layout],
);
const itemClass = computed(
    () =>
        ({
            list: 'flex min-w-0 items-center gap-2 rounded-md bg-card',
            grid: 'flex min-w-0 flex-col gap-2 rounded-md bg-card p-2',
            row: 'flex w-40 shrink-0 flex-col gap-2 rounded-md bg-card p-2',
        })[props.layout],
);
const cutClass = computed(() =>
    props.layout === 'row'
        ? 'flex shrink-0 items-center self-stretch border-l-2 border-dashed border-outline px-2 text-xs text-muted-foreground [writing-mode:vertical-rl]'
        : 'col-span-full flex items-center gap-2 text-xs text-muted-foreground before:h-px before:flex-1 before:bg-outline after:h-px after:flex-1 after:bg-outline',
);

const reorderer = createReorder({
    get: () => ids.value,
    set: (next) => {
        shown.value = next
            .map(
                (id) =>
                    props.items.find((item) => String(item.id) === id) ??
                    shown.value.find((item) => String(item.id) === id),
            )
            .filter((item): item is T => item !== undefined);
    },
    version: () => token,
    send: (order, version) =>
        sendOrder(props.reorder as ReorderAction, order, version),
    saved: (version) => {
        token = version;
        emit('saved', version);
    },
    rolledBack: (reason) => {
        announcement.value =
            reason === 'conflict'
                ? 'The order changed elsewhere, so the move was not saved.'
                : 'The move could not be saved.';
        if (reason === 'conflict') {
            if (props.onConflict) props.onConflict(ids.value);
            else emit('conflict', ids.value);
        } else emit('failed');
    },
    busy: (value) => {
        busy.value = value;
    },
});

async function move(from: number, to: number): Promise<void> {
    if (off.value || from === to || to < 0 || to >= shown.value.length) return;
    const item = shown.value[from];
    const next = moveItem(ids.value, from, to);
    announcement.value = '';
    if (props.reorder) {
        void reorderer.apply(next);
    } else {
        shown.value = next.map(
            (id) => shown.value.find((row) => String(row.id) === id) as T,
        );
        emit('update', next);
        emit('update:items', [...shown.value]);
    }
    await nextTick();
    announcement.value = moveAnnouncement(
        props.itemLabel(item, to),
        to,
        shown.value.length,
    );
}

const { container, onGripKeydown } = useSortable({ ids, move, disabled: off });

async function moveBy(
    index: number,
    offset: number,
    event: Event,
): Promise<void> {
    const button = event.currentTarget as HTMLElement | null;
    await move(index, index + offset);
    await nextTick();
    if (button && !button.isConnected)
        container.value
            ?.querySelectorAll<HTMLElement>('[data-sortable-item]')
            [index + offset]?.querySelector<HTMLElement>(
                '[data-sortable-handle]',
            )
            ?.focus();
    else button?.focus();
}
</script>
<template>
    <div data-sortable-list>
        <ul
            ref="container"
            :aria-label="label"
            :class="['m-0 list-none p-0', listClass]"
            :aria-busy="busy || undefined"
            :data-sortable-layout="layout"
        >
            <template v-for="(item, index) in shown" :key="item.id">
                <li
                    v-if="cutAt === index"
                    role="separator"
                    :aria-label="
                        cutLabel ?? `Only the first ${cutAt} are shown`
                    "
                    :class="cutClass"
                    data-sortable-cut
                >
                    {{ cutLabel ?? `Only the first ${cutAt} are shown` }}
                </li>
                <li
                    :class="itemClass"
                    data-sortable-item
                    :data-sortable-id="item.id"
                >
                    <div
                        :class="
                            flow
                                ? 'flex items-center justify-between gap-1'
                                : 'contents'
                        "
                    >
                        <SortableHandle
                            :label="itemLabel(item, index)"
                            :disabled="off"
                            @keydown="(event) => onGripKeydown(index, event)"
                        />
                        <span
                            v-if="moveButtons && flow"
                            class="flex shrink-0 items-center gap-0.5"
                        >
                            <button
                                type="button"
                                :aria-disabled="off || index === 0 || undefined"
                                :aria-label="`Move ${itemLabel(item, index)} back`"
                                title="Move back"
                                class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none aria-disabled:pointer-events-none aria-disabled:opacity-30"
                                data-sortable-up
                                @click="moveBy(index, -1, $event)"
                            >
                                <ArrowLeft class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                :aria-disabled="
                                    off ||
                                    index === shown.length - 1 ||
                                    undefined
                                "
                                :aria-label="`Move ${itemLabel(item, index)} forward`"
                                title="Move forward"
                                class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none aria-disabled:pointer-events-none aria-disabled:opacity-30"
                                data-sortable-down
                                @click="moveBy(index, 1, $event)"
                            >
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </button>
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <slot :item="item" :index="index" />
                    </div>
                    <span
                        v-if="moveButtons && !flow"
                        class="flex shrink-0 items-center gap-0.5"
                    >
                        <button
                            type="button"
                            :aria-disabled="off || index === 0 || undefined"
                            :aria-label="`Move ${itemLabel(item, index)} up`"
                            title="Move up"
                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none aria-disabled:pointer-events-none aria-disabled:opacity-30"
                            data-sortable-up
                            @click="moveBy(index, -1, $event)"
                        >
                            <ArrowUp class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :aria-disabled="
                                off || index === shown.length - 1 || undefined
                            "
                            :aria-label="`Move ${itemLabel(item, index)} down`"
                            title="Move down"
                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none aria-disabled:pointer-events-none aria-disabled:opacity-30"
                            data-sortable-down
                            @click="moveBy(index, 1, $event)"
                        >
                            <ArrowDown class="size-4" aria-hidden="true" />
                        </button>
                    </span>
                </li>
            </template>
        </ul>
        <p
            class="sr-only"
            role="status"
            aria-live="polite"
            data-sortable-status
        >
            {{ announcement }}
        </p>
    </div>
</template>
