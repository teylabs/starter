<script setup lang="ts">
import {
    computed,
    inject,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    useId,
    watch,
    watchEffect,
} from 'vue';
import { parseDate, type DateValue } from '@internationalized/date';
import {
    DateRangePickerCalendar,
    DateRangePickerCell,
    DateRangePickerCellTrigger,
    DateRangePickerContent,
    DateRangePickerField,
    DateRangePickerGrid,
    DateRangePickerGridBody,
    DateRangePickerGridHead,
    DateRangePickerGridRow,
    DateRangePickerHeadCell,
    DateRangePickerHeader,
    DateRangePickerHeading,
    DateRangePickerInput,
    DateRangePickerNext,
    DateRangePickerPrev,
    DateRangePickerRoot,
    DateRangePickerTrigger,
} from 'reka-ui';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import Field from '@/components/Kit/Form/Field.vue';
import { useLayerTarget } from '@/components/Kit/Overlay/layer';
import { todayIn } from '@/components/Kit/Data/dateTime';
import { useTimezone, viewerLocale } from '@/components/Kit/Data/useTimezone';
import { isDate, rangeError, type DateRange } from './dateInput';
import { rowErrorsKey } from './repeaterState';
import { useLocked } from './useLocked';

// A span of calendar dates as two plain `YYYY-MM-DD` values, submitted as `name[start]` and `name[end]`: typed segment by segment or chosen as two clicks on one calendar
// (the APG date picker dialog keys). The ends are wall dates, never converted between zones; `kitTimezone` only decides which day is today. A half-filled or reversed range
// is reported on the field and through the browser's validity. On phones the calendar opens as a bottom sheet; inside a locked LockedForm it does not open.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        id?: string;
        defaultValue?: DateRange;
        min?: string;
        max?: string;
        description?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
    }>(),
    { defaultValue: () => ({ start: '', end: '' }) },
);
const emit = defineEmits<{ canonical: [value: DateRange] }>();
const id = props.id ?? useId();
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const timezone = useTimezone();
const locale = viewerLocale();
const layerTarget = useLayerTarget();
const toDate = (value: string | undefined) =>
    value && isDate(value) ? parseDate(value) : undefined;
const toRange = (range: DateRange) => ({
    start: toDate(range.start),
    end: toDate(range.end),
});
const value = shallowRef<{
    start: DateValue | undefined;
    end: DateValue | undefined;
}>(toRange(props.defaultValue));
const canonical = computed<DateRange>(() => ({
    start: value.value.start?.toString().slice(0, 10) ?? '',
    end: value.value.end?.toString().slice(0, 10) ?? '',
}));
const hint = computed(() =>
    rangeError(canonical.value, isDate, {
        incomplete: 'Choose both dates.',
        order: 'The end date is before the start date.',
    }),
);
const today = computed(() => parseDate(todayIn(timezone.value)));
const form = useOptionalFormContext();
const rowErrors = inject(rowErrorsKey, undefined);
const dotted = props.name.replace(/\[([^\]]+)\]/g, '.$1');
const serverError = computed(
    () =>
        rowErrors?.value[props.name] ??
        form?.errors?.[dotted] ??
        form?.errors?.[`${dotted}.start`] ??
        form?.errors?.[`${dotted}.end`],
);
const error = computed(() => props.error || hint.value || serverError.value);
const hidden = ref<HTMLInputElement>();
const root = ref<HTMLElement>();
async function update(
    next: { start?: DateValue; end?: DateValue } | null | undefined,
) {
    value.value = { start: next?.start, end: next?.end };
    emit('canonical', canonical.value);
    await nextTick();
    hidden.value?.dispatchEvent(new Event('input', { bubbles: true }));
}
const reset = () =>
    nextTick(() => {
        value.value = toRange(props.defaultValue);
    });
onMounted(() => root.value?.closest('form')?.addEventListener('reset', reset));
onBeforeUnmount(() =>
    root.value?.closest('form')?.removeEventListener('reset', reset),
);
watch(
    () => props.defaultValue,
    (v) => {
        value.value = toRange(v);
    },
    { deep: true },
);
watchEffect(() => hidden.value?.setCustomValidity(hint.value), {
    flush: 'post',
});
const segment =
    'inline-flex min-h-6 min-w-6 items-center justify-center rounded-sm px-0.5 tabular-nums outline-none focus:bg-accent focus:text-accent-foreground data-placeholder:text-muted-foreground';
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
            data-slot="input-date-range"
            :data-locked="locked || undefined"
        >
            <DateRangePickerRoot
                :id="id"
                close-on-select
                :model-value="value"
                :placeholder="value.start ?? today"
                :locale="locale"
                :min-value="toDate(min)"
                :max-value="toDate(max)"
                :disabled="disabled"
                :readonly="locked"
                :required="required"
                granularity="day"
                @update:model-value="update"
            >
                <DateRangePickerField
                    v-slot="{ segments }"
                    v-bind="$attrs"
                    :aria-labelledby="`${id}-label`"
                    :aria-invalid="Boolean(error) || undefined"
                    :aria-describedby="
                        [
                            description && `${id}-description`,
                            error && `${id}-error`,
                        ]
                            .filter(Boolean)
                            .join(' ') || undefined
                    "
                    :class="[
                        'focus-within:border-ring focus-within:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex h-9 w-full min-w-0 items-center gap-1 rounded-md border px-3 text-base transition-[color,box-shadow] focus-within:ring-[3px] data-disabled:cursor-not-allowed data-disabled:opacity-50 md:text-sm',
                        locked
                            ? 'border-border bg-muted shadow-none'
                            : 'border-input dark:bg-input/30 bg-transparent shadow-xs',
                    ]"
                >
                    <div class="flex min-w-0 flex-1 items-center">
                        <template
                            v-for="item in segments.start"
                            :key="`start-${item.part}`"
                        >
                            <DateRangePickerInput
                                v-if="item.part === 'literal'"
                                :part="item.part"
                                type="start"
                                class="text-muted-foreground"
                                >{{ item.value }}</DateRangePickerInput
                            >
                            <DateRangePickerInput
                                v-else
                                :part="item.part"
                                type="start"
                                :class="segment"
                                >{{ item.value }}</DateRangePickerInput
                            >
                        </template>
                        <span
                            aria-hidden="true"
                            class="text-muted-foreground px-1.5"
                            >–</span
                        >
                        <template
                            v-for="item in segments.end"
                            :key="`end-${item.part}`"
                        >
                            <DateRangePickerInput
                                v-if="item.part === 'literal'"
                                :part="item.part"
                                type="end"
                                class="text-muted-foreground"
                                >{{ item.value }}</DateRangePickerInput
                            >
                            <DateRangePickerInput
                                v-else
                                :part="item.part"
                                type="end"
                                :class="segment"
                                >{{ item.value }}</DateRangePickerInput
                            >
                        </template>
                    </div>
                    <DateRangePickerTrigger
                        v-if="!locked"
                        :aria-label="`Choose ${label}`"
                        class="text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring/50 -mr-1.5 inline-flex size-7 shrink-0 items-center justify-center rounded-sm focus-visible:ring-[3px] focus-visible:outline-none"
                    >
                        <CalendarDays class="size-4" aria-hidden="true" />
                    </DateRangePickerTrigger>
                </DateRangePickerField>
                <DateRangePickerContent
                    data-kit-sheet
                    :portal="{ to: layerTarget }"
                    :side-offset="4"
                    :collision-padding="8"
                    align="start"
                    class="border-border bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 z-[100] rounded-md border p-3 shadow-lg motion-reduce:animate-none"
                >
                    <DateRangePickerCalendar
                        v-slot="{ weekDays, grid }"
                        :calendar-label="label"
                        class="mx-auto w-max"
                    >
                        <DateRangePickerHeader
                            class="flex items-center justify-between gap-2 pb-2"
                        >
                            <DateRangePickerPrev
                                class="hover:bg-accent focus-visible:ring-ring/50 inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-[3px] focus-visible:outline-none"
                                aria-label="Previous month"
                                ><ChevronLeft class="size-4" aria-hidden="true"
                            /></DateRangePickerPrev>
                            <DateRangePickerHeading
                                class="text-sm font-medium"
                            />
                            <DateRangePickerNext
                                class="hover:bg-accent focus-visible:ring-ring/50 inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-[3px] focus-visible:outline-none"
                                aria-label="Next month"
                                ><ChevronRight
                                    class="size-4"
                                    aria-hidden="true"
                            /></DateRangePickerNext>
                        </DateRangePickerHeader>
                        <DateRangePickerGrid
                            v-for="month in grid"
                            :key="month.value.toString()"
                            class="border-collapse"
                        >
                            <DateRangePickerGridHead>
                                <DateRangePickerGridRow class="flex">
                                    <DateRangePickerHeadCell
                                        v-for="day in weekDays"
                                        :key="day"
                                        class="text-muted-foreground w-9 text-xs font-normal"
                                        >{{ day }}</DateRangePickerHeadCell
                                    >
                                </DateRangePickerGridRow>
                            </DateRangePickerGridHead>
                            <DateRangePickerGridBody>
                                <DateRangePickerGridRow
                                    v-for="(week, index) in month.rows"
                                    :key="index"
                                    class="mt-1 flex"
                                >
                                    <DateRangePickerCell
                                        v-for="day in week"
                                        :key="day.toString()"
                                        :date="day"
                                        class="p-0 text-center"
                                    >
                                        <DateRangePickerCellTrigger
                                            :day="day"
                                            :month="month.value"
                                            class="hover:bg-accent focus-visible:ring-ring/50 data-outside-view:text-muted-foreground data-highlighted:bg-accent data-selected:bg-primary/15 data-selection-start:bg-primary data-selection-start:text-primary-foreground data-selection-end:bg-primary data-selection-end:text-primary-foreground inline-flex size-9 items-center justify-center rounded-md text-sm tabular-nums outline-none focus-visible:ring-[3px] data-disabled:pointer-events-none data-disabled:opacity-40 data-today:font-semibold data-today:underline data-unavailable:line-through"
                                        />
                                    </DateRangePickerCell>
                                </DateRangePickerGridRow>
                            </DateRangePickerGridBody>
                        </DateRangePickerGrid>
                    </DateRangePickerCalendar>
                </DateRangePickerContent>
            </DateRangePickerRoot>
        </div>
        <!-- The start end is a visually hidden text input (not type=hidden) so its custom validity blocks a native submit of a half-filled or reversed range. -->
        <input
            ref="hidden"
            type="text"
            tabindex="-1"
            aria-hidden="true"
            class="sr-only"
            :name="`${name}[start]`"
            :value="canonical.start"
            :disabled="disabled"
        />
        <input
            type="hidden"
            :name="`${name}[end]`"
            :value="canonical.end"
            :disabled="disabled"
        />
    </Field>
</template>
