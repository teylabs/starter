<script setup lang="ts" generic="T extends RepeaterRow">
import {
    computed,
    inject,
    nextTick,
    onMounted,
    onBeforeUnmount,
    onBeforeUpdate,
    onUpdated,
    provide,
    ref,
    shallowRef,
    toRaw,
    useId,
    useSlots,
    watch,
} from 'vue';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import { ArrowDown, ArrowUp, MoreHorizontal, Plus, Trash2 } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import RepeaterRowProvider from './RepeaterRow.vue';
import SortableHandle from '@/components/Kit/Data/SortableHandle.vue';
import { useSortable } from '@/components/Kit/Data/sortable';
import {
    fieldPath,
    mappedErrors,
    preserveNativeEdits,
    repeaterDepthKey,
    rowErrorsKey,
    type RepeaterRow,
} from './repeaterState';
import { useLocked } from './useLocked';
const props = withDefaults(
    defineProps<{
        name: string;
        rows: T[];
        template?: Omit<T, 'id'>;
        limits?: { min?: number; max?: number };
        addLabel: string;
        itemLabel: string;
        reorderable?: boolean;
        draggable?: boolean;
        actionsInMenu?: boolean;
        removable?: boolean | { label: string; rowIds: string[] };
        errors?: Record<string, string>;
        flush?: boolean;
        externalAdd?: boolean;
        orderButtons?: boolean;
        columns?: string[];
        gridClass?: string;
        headerClass?: string;
        rowClass?: string;
    }>(),
    {
        reorderable: true,
        draggable: false,
        orderButtons: true,
        removable: true,
        columns: undefined,
        gridClass: '',
    },
);
// `columns` switches to the input-grid layout: one header row, each row's slot renders one cell per column, actions last. Narrow containers stack (container query, so a narrow pane stacks too); gridClass uses @lg:grid-cols-[…].
const table = computed(() => !!props.columns?.length);
// Room on the leading edge for the pinned grip in the table layout, on the rows and the header alike, so the columns still line up.
const gripRoom = computed(() =>
    table.value && props.reorderable && props.draggable && canEdit.value
        ? '@lg:pl-9'
        : '',
);
// Inside a locked LockedForm (the LockedForm spec) the edit actions are hidden, not disabled: add, remove and the move buttons; a `row-menu` slot keeps its menu, `row-actions` is the caller's (EditOnly).
const lock = useLocked();
const canEdit = computed(() => !lock.locked);
const emit = defineEmits<{ change: [ids: string[]] }>();
const depth = inject(repeaterDepthKey, 0);
if (depth > 1) throw new Error('Only two Repeater levels are supported.');
provide(repeaterDepthKey, depth + 1);
if (new Set(props.rows.map((r) => r.id)).size !== props.rows.length)
    throw new Error('Repeater row IDs must be unique.');
const initial = () =>
    props.rows.map((row, index) => ({
        row: structuredClone(toRaw(row)),
        original: index,
    }));
const entries = shallowRef(initial());
const root = ref<HTMLElement>();
let restoreEdits = () => {};
onBeforeUpdate(() => {
    restoreEdits = preserveNativeEdits(root.value);
});
onUpdated(() => restoreEdits());
const pending = ref<string | null>(null);
const removeOpen = ref(false);
const announcement = ref('');
const context = useOptionalFormContext();
const inheritedErrors = inject(rowErrorsKey, undefined);
const errors = computed(
    () =>
        props.errors ??
        (inheritedErrors
            ? Object.fromEntries(
                  Object.entries(inheritedErrors.value).map(([key, value]) => [
                      fieldPath(key),
                      value,
                  ]),
              )
            : (context?.errors ?? {})),
);
const limit = computed(() => ({
    min: props.limits?.min ?? 0,
    max: props.limits?.max ?? Infinity,
}));
const removeLabel = computed(() =>
    typeof props.removable === 'object' ? props.removable.label : 'Remove',
);
const canRemove = (id: string) =>
    props.removable === true ||
    (typeof props.removable === 'object' &&
        props.removable.rowIds.includes(id));
const path = (index: number) => `${props.name}[${index}]`;
function rowErrors(entry: (typeof entries.value)[number], index: number) {
    return entry.original < 0
        ? {}
        : mappedErrors(
              errors.value,
              `${props.name}[${entry.original}]`,
              path(index),
          );
}
async function changed(message: string) {
    announcement.value = '';
    emit(
        'change',
        entries.value.map((e) => e.row.id),
    );
    await nextTick();
    root.value?.dispatchEvent(new Event('input', { bubbles: true }));
    announcement.value = message;
}
defineExpose({ add: (overrides?: Partial<Omit<T, 'id'>>) => add(overrides) });
async function add(overrides: Partial<Omit<T, 'id'>> = {}) {
    if (
        !canEdit.value ||
        !props.template ||
        entries.value.length >= limit.value.max
    )
        return;
    const row = {
        ...structuredClone(toRaw(props.template)),
        ...overrides,
        id: `new:${crypto.randomUUID()}`,
    } as T;
    entries.value = [...entries.value, { row, original: -1 }];
    await changed(`${props.itemLabel} ${entries.value.length} added`);
    firstControl(
        rows.value?.querySelectorAll<HTMLElement>(
            ':scope > [data-repeater-row]',
        )[entries.value.length - 1],
    )?.focus();
}
// A host that fills `row-actions` (outside the table layout) owns the row's controls.
const slots = useSlots();
const hostActions = computed(
    () => !table.value && Boolean(slots['row-actions']),
);
// What `row-actions` gets: the moves, plus remove (null when the row can't be removed; it asks first, as the kit's own button does).
function rowControls(index: number, row: T) {
    const removable =
        canEdit.value &&
        canRemove(row.id) &&
        entries.value.length > limit.value.min;
    return {
        ...rowMoves(index, row),
        canRemove: removable,
        remove: removable ? () => askRemove(row.id, index) : null,
    };
}

// What the row-header and row-menu slots get: the row, its label, and its moves (null when the row can't move), so a host menu can offer Move up/down.
function rowMoves(index: number, row: T) {
    const movable = props.reorderable && canEdit.value;
    return {
        row,
        index,
        label: `${props.itemLabel} ${index + 1}`,
        first: index === 0,
        last: index === entries.value.length - 1,
        moveUp: movable && index > 0 ? () => move(index, -1) : null,
        moveDown:
            movable && index < entries.value.length - 1
                ? () => move(index, 1)
                : null,
    };
}

async function move(index: number, offset: number) {
    const target = index + offset;
    if (target < 0 || target >= entries.value.length) return;
    const list = [...entries.value];
    const [entry] = list.splice(index, 1);
    list.splice(target, 0, entry);
    const focused = document.activeElement as HTMLElement | null;
    entries.value = list;
    await changed(
        `${props.itemLabel} ${index + 1} moved to position ${target + 1}`,
    );
    focused?.focus();
}
// Reordering is by the move buttons (keyboard and touch). With `draggable`, each row also gets a grip: pointer drag, and ↑/↓ or Home/End while it is focused,
// through the kit's shared sortable; both go through move(), so the announcement and the change event are the same. The scope keeps a nested Repeater's rows
// and grips out of this one's drag.
const rows = ref<HTMLElement>();
const sortScope = useId();
const { onGripKeydown } = useSortable({
    ids: () => entries.value.map((entry) => entry.row.id),
    move: (from, to) => move(from, to - from),
    container: rows,
    itemSelector: `[data-repeater-row][data-sortable-scope="${sortScope}"]`,
    handleSelector: `[data-sortable-handle][data-sortable-scope="${sortScope}"]`,
    disabled: () => !(props.reorderable && props.draggable && canEdit.value),
});
let removeOpener: HTMLElement | null = null;
function askRemove(id: string, index: number) {
    removeOpener =
        rows.value
            ?.querySelectorAll<HTMLElement>(':scope > [data-repeater-row]')
            [index]?.querySelector<HTMLElement>(
                ':scope > div > [data-repeater-menu], :scope > div > [role=group] > [data-repeater-menu], :scope > div > [data-repeater-remove]',
            ) ?? null;
    const open = () => {
        pending.value = id;
        removeOpen.value = true;
    };
    if (props.actionsInMenu) setTimeout(open, 0);
    else open();
}
watch(removeOpen, (open) => {
    if (!open && pending.value !== null)
        requestAnimationFrame(() => removeOpener?.focus());
});
async function remove() {
    const index = entries.value.findIndex((e) => e.row.id === pending.value);
    pending.value = null;
    removeOpen.value = false;
    if (
        index < 0 ||
        !canRemove(entries.value[index].row.id) ||
        entries.value.length <= limit.value.min
    )
        return;
    entries.value = entries.value.filter((_, i) => i !== index);
    await changed(`${props.itemLabel} ${index + 1} removed`);
    // Wait for Dialog's focus restoration before selecting the next surviving row.
    requestAnimationFrame(() => {
        const list = rows.value?.querySelectorAll<HTMLElement>(
            ':scope > [data-repeater-row]',
        );
        const row = list?.[Math.min(index, (list?.length ?? 0) - 1)];
        (
            firstControl(row) ??
            root.value?.querySelector<HTMLElement>('[data-repeater-add]')
        )?.focus();
    });
}
const reset = () => {
    entries.value = initial();
    announcement.value = '';
};
onMounted(() => root.value?.closest('form')?.addEventListener('reset', reset));
onBeforeUnmount(() =>
    root.value?.closest('form')?.removeEventListener('reset', reset),
);
// A row's first field (the row toolbar's buttons come first in the DOM), else its first button.
function firstControl(row: Element | null | undefined): HTMLElement | null {
    return (
        row?.querySelector<HTMLElement>(
            'input:not([type=hidden]),select,textarea',
        ) ??
        row?.querySelector<HTMLElement>('button') ??
        null
    );
}
</script>
<template>
    <div
        ref="root"
        :class="table ? '@container grid min-w-0 gap-2' : 'grid min-w-0 gap-4'"
        :aria-label="addLabel"
    >
        <div
            v-if="table && entries.length"
            aria-hidden="true"
            :class="[
                'border-border text-muted-foreground hidden gap-x-2 border-b pb-1.5 text-xs font-medium @lg:grid',
                gripRoom,
                gridClass,
                headerClass,
            ]"
        >
            <span v-for="column in columns" :key="column">{{ column }}</span
            ><span />
        </div>
        <slot name="before-rows" />
        <div
            ref="rows"
            data-repeater-rows
            :class="table ? 'grid min-w-0 gap-2' : 'grid min-w-0 gap-4'"
        >
            <section
                v-for="(entry, index) in entries"
                :key="entry.row.id"
                data-repeater-row
                :data-sortable-scope="sortScope"
                :class="
                    table
                        ? [
                              'border-border relative grid min-w-0 gap-2 rounded-lg border p-3 @lg:items-start @lg:gap-x-2 @lg:rounded-none @lg:border-0 @lg:border-b @lg:p-0 @lg:py-2 @lg:[&_label]:sr-only',
                              gripRoom,
                              gridClass,
                              rowClass,
                          ]
                        : flush
                          ? 'border-border bg-card text-card-foreground grid min-w-0 overflow-hidden rounded-lg border shadow-xs'
                          : 'border-border bg-card text-card-foreground grid min-w-0 gap-4 rounded-lg border p-4'
                "
            >
                <div
                    :class="
                        table
                            ? 'flex items-center gap-1 @lg:order-last @lg:pt-1'
                            : flush
                              ? 'border-border flex items-center gap-1 border-b px-4 py-2'
                              : '-mt-1 -mr-2 flex items-center gap-1'
                    "
                    data-repeater-toolbar
                >
                    <!-- The grip leads the row (the kit's sortable convention): first in a card's toolbar, and pinned to the row's leading edge in the table layout. -->
                    <SortableHandle
                        v-if="reorderable && canEdit && draggable"
                        :label="`${itemLabel} ${index + 1}`"
                        :data-sortable-scope="sortScope"
                        :class="table && '@lg:absolute @lg:top-2 @lg:left-0'"
                        data-repeater-grip
                        @keydown="(event) => onGripKeydown(index, event)"
                    />
                    <!-- `row-header` replaces the row's title with the host's own header (a SectionHeader: title, meta, actions); the grip leads it and the move and remove controls follow, as Repeater's own. -->
                    <div
                        v-if="$slots['row-header']"
                        class="min-w-0 flex-1"
                        data-repeater-row-header
                    >
                        <slot
                            name="row-header"
                            v-bind="rowMoves(index, entry.row)"
                        />
                    </div>
                    <h3
                        v-else
                        :class="
                            table
                                ? 'min-w-0 flex-1 text-sm font-medium @lg:sr-only'
                                : flush
                                  ? 'min-w-0 flex-1 truncate text-base font-semibold'
                                  : 'min-w-0 flex-1 font-medium'
                        "
                    >
                        <slot
                            name="row-title"
                            v-bind="{ row: entry.row, index }"
                            >{{ itemLabel }} {{ index + 1 }}</slot
                        >
                    </h3>
                    <template
                        v-if="
                            !hostActions &&
                            reorderable &&
                            canEdit &&
                            (orderButtons || !draggable)
                        "
                    >
                        <button
                            type="button"
                            :aria-disabled="index === 0"
                            :aria-label="`Move ${itemLabel} ${index + 1} up`"
                            title="Move up"
                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-2 aria-disabled:pointer-events-none aria-disabled:opacity-30"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :aria-disabled="index === entries.length - 1"
                            :aria-label="`Move ${itemLabel} ${index + 1} down`"
                            title="Move down"
                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-2 aria-disabled:pointer-events-none aria-disabled:opacity-30"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-4" aria-hidden="true" />
                        </button>
                    </template>
                    <!-- A filled `row-actions` is the host's toolbar, rendered bare: the Repeater draws none of its own controls there (it hands them over as slot props); the grip stays. -->
                    <slot
                        v-if="hostActions"
                        name="row-actions"
                        v-bind="rowControls(index, entry.row)"
                    />
                    <div
                        v-else
                        class="contents"
                        role="group"
                        :aria-label="`${itemLabel} ${index + 1} actions`"
                    >
                        <DropdownMenu
                            :modal="false"
                            v-if="
                                actionsInMenu &&
                                ($slots['row-menu'] ||
                                    (canEdit &&
                                        canRemove(entry.row.id) &&
                                        entries.length > limit.min))
                            "
                        >
                            <DropdownMenuTrigger as-child
                                ><button
                                    type="button"
                                    class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-2 aria-disabled:pointer-events-none aria-disabled:opacity-30"
                                    data-repeater-menu
                                    :aria-label="`Open options for ${itemLabel} ${index + 1}`"
                                    title="More"
                                >
                                    <MoreHorizontal
                                        class="size-4"
                                        aria-hidden="true"
                                    /></button
                            ></DropdownMenuTrigger>
                            <DropdownMenuContent align="end"
                                ><slot
                                    name="row-menu"
                                    v-bind="rowMoves(index, entry.row)"
                                /><DropdownMenuItem
                                    v-if="
                                        canEdit &&
                                        canRemove(entry.row.id) &&
                                        entries.length > limit.min
                                    "
                                    variant="destructive"
                                    class="text-destructive-text focus:text-destructive-text"
                                    @select="askRemove(entry.row.id, index)"
                                    >{{ removeLabel }}</DropdownMenuItem
                                ></DropdownMenuContent
                            >
                        </DropdownMenu>
                    </div>
                    <button
                        v-if="
                            !actionsInMenu &&
                            canEdit &&
                            canRemove(entry.row.id) &&
                            entries.length > limit.min
                        "
                        type="button"
                        data-repeater-remove
                        :aria-label="`${removeLabel} ${itemLabel} ${index + 1}`"
                        :title="removeLabel"
                        class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring hover:text-destructive-text inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-2 aria-disabled:pointer-events-none aria-disabled:opacity-30"
                        @click="askRemove(entry.row.id, index)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p
                    v-if="Object.keys(rowErrors(entry, index)).length"
                    role="alert"
                    :class="[
                        'text-destructive-text text-sm',
                        table && '@lg:order-last @lg:col-span-full',
                    ]"
                >
                    {{ Object.keys(rowErrors(entry, index)).length }} problems
                    in {{ itemLabel }} {{ index + 1 }}
                </p>
                <input
                    type="hidden"
                    :name="`${path(index)}[id]`"
                    :value="entry.row.id"
                />
                <RepeaterRowProvider :errors="rowErrors(entry, index)">
                    <slot
                        name="row"
                        v-bind="{
                            row: entry.row,
                            index,
                            name: (field: string) => `${path(index)}[${field}]`,
                            errors: rowErrors(entry, index),
                        }"
                    />
                </RepeaterRowProvider>
            </section>
        </div>
        <slot v-if="!entries.length" name="empty" />
        <button
            v-if="
                canEdit &&
                template &&
                entries.length < limit.max &&
                !externalAdd
            "
            data-repeater-add
            type="button"
            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex min-h-9 items-center gap-1.5 justify-self-start rounded-md px-2 text-sm font-medium focus-visible:ring-2"
            @click="add()"
        >
            <Plus class="size-4" aria-hidden="true" />{{ addLabel }}
        </button>
        <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">
            {{ announcement }}
        </p>
        <Dialog
            v-model:open="removeOpen"
            :title="`${removeLabel} ${itemLabel} ${entries.findIndex((e) => e.row.id === pending) + 1}?`"
            :confirm-label="removeLabel"
            variant="danger"
            @confirm="remove"
        />
    </div>
</template>
