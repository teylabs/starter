<script setup lang="ts">
import {
    computed,
    inject,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import Field from '@/components/Kit/Form/Field.vue';
import { useLayerTarget } from '@/components/Kit/Overlay/layer';
import { todayIn } from '@/components/Kit/Data/dateTime';
import { useTimezone, viewerLocale } from '@/components/Kit/Data/useTimezone';
import { gridMove, isMonth, isYear, monthNames } from './dateInput';
import { rowErrorsKey } from './repeaterState';
import { useLocked } from './useLocked';

// InputMonth and InputYear: a button showing the chosen month (`YYYY-MM`) or year (`YYYY`) that opens a 3×4 grid (the APG grid: arrows, Home/End, Control+Home/End,
// PageUp/PageDown turn the year or the dozen years, Enter or Space chooses, Escape closes and focus returns to the button). Wall values, never converted between zones;
// `kitTimezone` only decides the current month or year. On phones the grid opens as a bottom sheet; inside a locked LockedForm the button does not open it.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        mode: 'month' | 'year';
        name: string;
        label: string;
        id?: string;
        defaultValue?: string;
        min?: string;
        max?: string;
        placeholder?: string;
        description?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
    }>(),
    { defaultValue: '' },
);
const emit = defineEmits<{ canonical: [value: string] }>();
const id = props.id ?? useId();
const gridId = `${id}-grid`;
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const timezone = useTimezone();
const locale = viewerLocale();
const layerTarget = useLayerTarget();
const valid = (value: string) =>
    props.mode === 'month' ? isMonth(value) : isYear(value);
const value = ref(valid(props.defaultValue) ? props.defaultValue : '');
const today = computed(() => todayIn(timezone.value));
const current = computed(() =>
    props.mode === 'month' ? today.value.slice(0, 7) : today.value.slice(0, 4),
);
const open = ref(false);
// The page: a year for the month grid, the first of twelve years for the year grid.
const anchor = computed(() =>
    Number((value.value || current.value).slice(0, 4)),
);
const page = ref(0);
const focused = ref(0);
const firstYear = (year: number) => year - (((year % 12) + 12) % 12);
function resetPage() {
    page.value =
        props.mode === 'month' ? anchor.value : firstYear(anchor.value);
    const chosen = value.value || current.value;
    focused.value =
        props.mode === 'month'
            ? Number(chosen.slice(5, 7)) - 1
            : Number(chosen.slice(0, 4)) - page.value;
}
resetPage();
const months = computed(() => monthNames(locale, 'short'));
const longMonths = computed(() => monthNames(locale, 'long'));
const cells = computed(() =>
    Array.from({ length: 12 }, (_, i) => {
        const key =
            props.mode === 'month'
                ? `${page.value}-${String(i + 1).padStart(2, '0')}`
                : String(page.value + i).padStart(4, '0');
        const text = props.mode === 'month' ? months.value[i] : key;
        const full =
            props.mode === 'month'
                ? `${longMonths.value[i]} ${page.value}`
                : key;
        const outOfRange =
            (props.min !== undefined && key < props.min) ||
            (props.max !== undefined && key > props.max);

        return {
            key,
            text,
            full,
            outOfRange,
            selected: key === value.value,
            current: key === current.value,
        };
    }),
);
const heading = computed(() =>
    props.mode === 'month'
        ? String(page.value)
        : `${page.value} – ${page.value + 11}`,
);
const display = computed(() => {
    if (!value.value) return '';
    if (props.mode === 'year') return value.value;

    return new Intl.DateTimeFormat(locale, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(
        new Date(
            Date.UTC(
                Number(value.value.slice(0, 4)),
                Number(value.value.slice(5, 7)) - 1,
                1,
            ),
        ),
    );
});
const form = useOptionalFormContext();
const rowErrors = inject(rowErrorsKey, undefined);
const serverError = computed(
    () =>
        rowErrors?.value[props.name] ??
        form?.errors?.[props.name.replace(/\[([^\]]+)\]/g, '.$1')],
);
const error = computed(() => props.error || serverError.value);
const hidden = ref<HTMLInputElement>();
const root = ref<HTMLElement>();
const grid = ref<HTMLElement>();
const step = props.mode === 'month' ? 1 : 12;
function focusCell() {
    nextTick(() =>
        grid.value
            ?.querySelector<HTMLElement>(`[data-index="${focused.value}"]`)
            ?.focus(),
    );
}
function turn(direction: -1 | 1) {
    page.value += direction * step;
}
function keydown(event: KeyboardEvent) {
    const next = gridMove(
        focused.value,
        event.key,
        3,
        12,
        event.ctrlKey || event.metaKey,
    );
    if (next === null) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            choose(cells.value[focused.value]);
        }

        return;
    }
    event.preventDefault();
    if (next < 0 || next >= 12) turn(next < 0 ? -1 : 1);
    focused.value = ((next % 12) + 12) % 12;
    focusCell();
}
async function choose(cell: { key: string; outOfRange: boolean }) {
    if (cell.outOfRange) return;
    value.value = cell.key;
    open.value = false;
    emit('canonical', value.value);
    await nextTick();
    hidden.value?.dispatchEvent(new Event('input', { bubbles: true }));
}
watch(open, (isOpen) => {
    if (isOpen) {
        resetPage();
        focusCell();
    }
});
const reset = () =>
    nextTick(() => {
        value.value = valid(props.defaultValue) ? props.defaultValue : '';
    });
onMounted(() => root.value?.closest('form')?.addEventListener('reset', reset));
onBeforeUnmount(() =>
    root.value?.closest('form')?.removeEventListener('reset', reset),
);
watch(
    () => props.defaultValue,
    (v) => {
        value.value = valid(v) ? v : '';
    },
);
const navButton =
    'inline-flex size-8 items-center justify-center rounded-md hover:bg-accent focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>
<template>
    <Field
        :id="id"
        :name="name"
        :label="label"
        :description="description"
        :error="error || undefined"
        class="min-w-0"
    >
        <div
            ref="root"
            :data-slot="mode === 'month' ? 'input-month' : 'input-year'"
            :data-locked="locked || undefined"
        >
            <PopoverRoot v-model:open="open">
                <PopoverTrigger
                    :id="id"
                    v-bind="$attrs"
                    type="button"
                    :disabled="disabled || locked"
                    :aria-haspopup="'dialog'"
                    :aria-invalid="Boolean(error) || undefined"
                    :aria-required="required || undefined"
                    :aria-describedby="
                        [
                            description && `${id}-description`,
                            error && `${id}-error`,
                        ]
                            .filter(Boolean)
                            .join(' ') || undefined
                    "
                    :class="[
                        'focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border px-3 text-left text-base transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed md:text-sm',
                        locked
                            ? 'border-border bg-muted shadow-none disabled:cursor-default'
                            : 'border-input dark:bg-input/30 bg-transparent shadow-xs disabled:opacity-50',
                    ]"
                >
                    <span
                        :class="[
                            'truncate',
                            !display && 'text-muted-foreground',
                        ]"
                        >{{
                            display ||
                            placeholder ||
                            (mode === 'month'
                                ? 'Choose a month'
                                : 'Choose a year')
                        }}</span
                    >
                    <CalendarDays
                        v-if="!locked"
                        class="text-muted-foreground size-4 shrink-0"
                        aria-hidden="true"
                    />
                </PopoverTrigger>
                <PopoverPortal :to="layerTarget">
                    <PopoverContent
                        data-kit-sheet
                        :aria-label="label"
                        :side-offset="4"
                        :collision-padding="8"
                        align="start"
                        class="border-border bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 z-[100] rounded-md border p-3 shadow-lg motion-reduce:animate-none"
                        @open-auto-focus.prevent
                    >
                        <div class="mx-auto w-max">
                            <div
                                class="flex items-center justify-between gap-2 pb-2"
                            >
                                <button
                                    type="button"
                                    :class="navButton"
                                    :aria-label="
                                        mode === 'month'
                                            ? 'Previous year'
                                            : 'Previous years'
                                    "
                                    @click="turn(-1)"
                                >
                                    <ChevronLeft
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                                <span
                                    class="text-sm font-medium"
                                    aria-live="polite"
                                    >{{ heading }}</span
                                >
                                <button
                                    type="button"
                                    :class="navButton"
                                    :aria-label="
                                        mode === 'month'
                                            ? 'Next year'
                                            : 'Next years'
                                    "
                                    @click="turn(1)"
                                >
                                    <ChevronRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                            <div
                                :id="gridId"
                                ref="grid"
                                role="grid"
                                :aria-label="heading"
                                class="grid grid-cols-3 gap-1"
                                @keydown="keydown"
                            >
                                <div
                                    v-for="row in 4"
                                    :key="row"
                                    role="row"
                                    class="contents"
                                >
                                    <button
                                        v-for="(cell, column) in cells.slice(
                                            (row - 1) * 3,
                                            row * 3,
                                        )"
                                        :key="cell.key"
                                        type="button"
                                        role="gridcell"
                                        :data-index="(row - 1) * 3 + column"
                                        :tabindex="
                                            (row - 1) * 3 + column === focused
                                                ? 0
                                                : -1
                                        "
                                        :aria-selected="cell.selected"
                                        :aria-disabled="
                                            cell.outOfRange || undefined
                                        "
                                        :aria-label="cell.full"
                                        :aria-current="
                                            cell.current ? 'date' : undefined
                                        "
                                        :data-selected="
                                            cell.selected || undefined
                                        "
                                        :data-today="cell.current || undefined"
                                        class="hover:bg-accent focus-visible:ring-ring/50 data-selected:bg-primary data-selected:text-primary-foreground inline-flex h-9 w-20 items-center justify-center rounded-md text-sm tabular-nums outline-none focus-visible:ring-[3px] aria-disabled:pointer-events-none aria-disabled:opacity-40 data-today:font-semibold data-today:underline"
                                        @click="
                                            focused = (row - 1) * 3 + column;
                                            choose(cell);
                                        "
                                    >
                                        {{ cell.text }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </PopoverContent>
                </PopoverPortal>
            </PopoverRoot>
        </div>
        <input
            ref="hidden"
            type="hidden"
            :name="name"
            :value="value"
            :disabled="disabled"
        />
    </Field>
</template>
