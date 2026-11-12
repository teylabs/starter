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
} from 'vue';
import {
    parseDate,
    type CalendarDate,
    type DateValue,
} from '@internationalized/date';
import {
    DatePickerCalendar,
    DatePickerCell,
    DatePickerCellTrigger,
    DatePickerContent,
    DatePickerField,
    DatePickerGrid,
    DatePickerGridBody,
    DatePickerGridHead,
    DatePickerGridRow,
    DatePickerHeadCell,
    DatePickerHeader,
    DatePickerHeading,
    DatePickerInput,
    DatePickerNext,
    DatePickerPrev,
    DatePickerRoot,
    DatePickerTrigger,
} from 'reka-ui';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import Field from '@/components/Kit/Form/Field.vue';
import { useLayerTarget } from '@/components/Kit/Overlay/layer';
import { todayIn } from '@/components/Kit/Data/dateTime';
import { useTimezone, viewerLocale } from '@/components/Kit/Data/useTimezone';
import { isDate } from './dateInput';
import { rowErrorsKey } from './repeaterState';
import { useLocked } from './useLocked';

// A calendar date as a plain `YYYY-MM-DD`: typed segment by segment or chosen from a calendar (the APG date picker dialog: arrows move a day or a week, PageUp/PageDown a month, Home/End the week).
// The value is a wall date, never converted between zones; `kitTimezone` only decides which day is today. On phones the calendar opens as a bottom sheet.
// Inside a locked LockedForm the segments are read-only and the calendar does not open; the hidden canonical input stays.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        id?: string;
        defaultValue?: string;
        min?: string;
        max?: string;
        description?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
    }>(),
    { defaultValue: '' },
);
const emit = defineEmits<{ canonical: [value: string] }>();
const id = props.id ?? useId();
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const timezone = useTimezone();
const locale = viewerLocale();
const layerTarget = useLayerTarget();
const toDate = (value: string | undefined) =>
    value && isDate(value) ? parseDate(value) : undefined;
const value = shallowRef<CalendarDate | undefined>(toDate(props.defaultValue));
const canonical = computed(() => value.value?.toString() ?? '');
const today = computed(() => parseDate(todayIn(timezone.value)));
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
async function update(next: DateValue | undefined) {
    value.value = next ? parseDate(next.toString().slice(0, 10)) : undefined;
    emit('canonical', canonical.value);
    await nextTick();
    hidden.value?.dispatchEvent(new Event('input', { bubbles: true }));
}
const reset = () =>
    nextTick(() => {
        value.value = toDate(props.defaultValue);
    });
onMounted(() => root.value?.closest('form')?.addEventListener('reset', reset));
onBeforeUnmount(() =>
    root.value?.closest('form')?.removeEventListener('reset', reset),
);
watch(
    () => props.defaultValue,
    (v) => {
        value.value = toDate(v);
    },
);
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
            data-slot="input-date"
            :data-locked="locked || undefined"
        >
            <DatePickerRoot
                :id="id"
                close-on-select
                :model-value="value"
                :placeholder="value ?? today"
                :locale="locale"
                :min-value="toDate(min)"
                :max-value="toDate(max)"
                :disabled="disabled"
                :readonly="locked"
                :required="required"
                granularity="day"
                @update:model-value="update"
            >
                <DatePickerField
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
                        <template v-for="item in segments" :key="item.part">
                            <DatePickerInput
                                v-if="item.part === 'literal'"
                                :part="item.part"
                                class="text-muted-foreground"
                                >{{ item.value }}</DatePickerInput
                            >
                            <DatePickerInput
                                v-else
                                :part="item.part"
                                :class="segment"
                                >{{ item.value }}</DatePickerInput
                            >
                        </template>
                    </div>
                    <DatePickerTrigger
                        v-if="!locked"
                        :aria-label="`Choose ${label}`"
                        class="text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring/50 -mr-1.5 inline-flex size-7 shrink-0 items-center justify-center rounded-sm focus-visible:ring-[3px] focus-visible:outline-none"
                    >
                        <CalendarDays class="size-4" aria-hidden="true" />
                    </DatePickerTrigger>
                </DatePickerField>
                <DatePickerContent
                    data-kit-sheet
                    :portal="{ to: layerTarget }"
                    :side-offset="4"
                    :collision-padding="8"
                    align="start"
                    class="border-border bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 z-[100] rounded-md border p-3 shadow-lg motion-reduce:animate-none"
                >
                    <DatePickerCalendar
                        v-slot="{ weekDays, grid }"
                        :calendar-label="label"
                        class="mx-auto w-max"
                    >
                        <DatePickerHeader
                            class="flex items-center justify-between gap-2 pb-2"
                        >
                            <DatePickerPrev
                                class="hover:bg-accent focus-visible:ring-ring/50 inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-[3px] focus-visible:outline-none"
                                aria-label="Previous month"
                                ><ChevronLeft class="size-4" aria-hidden="true"
                            /></DatePickerPrev>
                            <DatePickerHeading class="text-sm font-medium" />
                            <DatePickerNext
                                class="hover:bg-accent focus-visible:ring-ring/50 inline-flex size-8 items-center justify-center rounded-md focus-visible:ring-[3px] focus-visible:outline-none"
                                aria-label="Next month"
                                ><ChevronRight
                                    class="size-4"
                                    aria-hidden="true"
                            /></DatePickerNext>
                        </DatePickerHeader>
                        <DatePickerGrid
                            v-for="month in grid"
                            :key="month.value.toString()"
                            class="border-collapse"
                        >
                            <DatePickerGridHead>
                                <DatePickerGridRow class="flex">
                                    <DatePickerHeadCell
                                        v-for="day in weekDays"
                                        :key="day"
                                        class="text-muted-foreground w-9 text-xs font-normal"
                                        >{{ day }}</DatePickerHeadCell
                                    >
                                </DatePickerGridRow>
                            </DatePickerGridHead>
                            <DatePickerGridBody>
                                <DatePickerGridRow
                                    v-for="(week, index) in month.rows"
                                    :key="index"
                                    class="mt-1 flex"
                                >
                                    <DatePickerCell
                                        v-for="day in week"
                                        :key="day.toString()"
                                        :date="day"
                                        class="p-0 text-center"
                                    >
                                        <DatePickerCellTrigger
                                            :day="day"
                                            :month="month.value"
                                            class="hover:bg-accent focus-visible:ring-ring/50 data-outside-view:text-muted-foreground data-selected:bg-primary data-selected:text-primary-foreground inline-flex size-9 items-center justify-center rounded-md text-sm tabular-nums outline-none focus-visible:ring-[3px] data-disabled:pointer-events-none data-disabled:opacity-40 data-today:font-semibold data-today:underline data-unavailable:line-through"
                                        />
                                    </DatePickerCell>
                                </DatePickerGridRow>
                            </DatePickerGridBody>
                        </DatePickerGrid>
                    </DatePickerCalendar>
                </DatePickerContent>
            </DatePickerRoot>
        </div>
        <input
            ref="hidden"
            type="hidden"
            :name="name"
            :value="canonical"
            :disabled="disabled"
        />
    </Field>
</template>
