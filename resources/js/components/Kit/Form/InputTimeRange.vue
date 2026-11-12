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
import { parseTime, type Time } from '@internationalized/date';
import {
    TimeRangeFieldInput,
    TimeRangeFieldRoot,
    type TimeValue,
} from 'reka-ui';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import Field from '@/components/Kit/Form/Field.vue';
import { viewerLocale } from '@/components/Kit/Data/useTimezone';
import { isTime, normalizeTime, rangeError, type DateRange } from './dateInput';
import { rowErrorsKey } from './repeaterState';
import { useLocked } from './useLocked';

// A span of times of day as two plain 24-hour `HH:MM` values, submitted as `name[start]` and `name[end]` (opening hours, a shift). Wall times, never converted between
// zones; the end must not be before the start, and a half-filled or reversed span blocks a native submit. Inside a locked LockedForm the segments are read-only.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        id?: string;
        defaultValue?: DateRange;
        min?: string;
        max?: string;
        step?: number;
        description?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
    }>(),
    { defaultValue: () => ({ start: '', end: '' }), step: 1 },
);
const emit = defineEmits<{ canonical: [value: DateRange] }>();
const id = props.id ?? useId();
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const locale = viewerLocale();
const toTime = (value: string | undefined) =>
    value && isTime(value) ? parseTime(normalizeTime(value)) : undefined;
const toRange = (range: DateRange) => ({
    start: toTime(range.start),
    end: toTime(range.end),
});
const value = shallowRef<{ start: Time | undefined; end: Time | undefined }>(
    toRange(props.defaultValue),
);
const canonical = computed<DateRange>(() => ({
    start: value.value.start?.toString().slice(0, 5) ?? '',
    end: value.value.end?.toString().slice(0, 5) ?? '',
}));
const hint = computed(() =>
    rangeError(canonical.value, isTime, {
        incomplete: 'Enter both times.',
        order: 'The end time is before the start time.',
    }),
);
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
const wall = (time: TimeValue | undefined) =>
    time
        ? parseTime(
              `${String(time.hour).padStart(2, '0')}:${String(time.minute).padStart(2, '0')}`,
          )
        : undefined;
async function update(
    next: { start?: TimeValue; end?: TimeValue } | null | undefined,
) {
    value.value = { start: wall(next?.start), end: wall(next?.end) };
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
            data-slot="input-time-range"
            :data-locked="locked || undefined"
        >
            <TimeRangeFieldRoot
                :id="id"
                v-slot="{ segments }"
                v-bind="$attrs"
                :model-value="value"
                :locale="locale"
                :min-value="toTime(min)"
                :max-value="toTime(max)"
                :step="{ minute: step }"
                :disabled="disabled"
                :readonly="locked"
                :required="required"
                granularity="minute"
                :aria-labelledby="`${id}-label`"
                :aria-invalid="Boolean(error) || undefined"
                :aria-describedby="
                    [description && `${id}-description`, error && `${id}-error`]
                        .filter(Boolean)
                        .join(' ') || undefined
                "
                :class="[
                    'focus-within:border-ring focus-within:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex h-9 w-full min-w-0 items-center rounded-md border px-3 text-base transition-[color,box-shadow] focus-within:ring-[3px] data-disabled:cursor-not-allowed data-disabled:opacity-50 md:text-sm',
                    locked
                        ? 'border-border bg-muted shadow-none'
                        : 'border-input dark:bg-input/30 bg-transparent shadow-xs',
                ]"
                @update:model-value="update"
            >
                <template
                    v-for="item in segments.start"
                    :key="`start-${item.part}`"
                >
                    <TimeRangeFieldInput
                        v-if="item.part === 'literal'"
                        :part="item.part"
                        type="start"
                        class="text-muted-foreground"
                        >{{ item.value }}</TimeRangeFieldInput
                    >
                    <TimeRangeFieldInput
                        v-else
                        :part="item.part"
                        type="start"
                        :class="segment"
                        >{{ item.value }}</TimeRangeFieldInput
                    >
                </template>
                <span aria-hidden="true" class="text-muted-foreground px-1.5"
                    >–</span
                >
                <template
                    v-for="item in segments.end"
                    :key="`end-${item.part}`"
                >
                    <TimeRangeFieldInput
                        v-if="item.part === 'literal'"
                        :part="item.part"
                        type="end"
                        class="text-muted-foreground"
                        >{{ item.value }}</TimeRangeFieldInput
                    >
                    <TimeRangeFieldInput
                        v-else
                        :part="item.part"
                        type="end"
                        :class="segment"
                        >{{ item.value }}</TimeRangeFieldInput
                    >
                </template>
            </TimeRangeFieldRoot>
        </div>
        <!-- The start end is a visually hidden text input (not type=hidden) so its custom validity blocks a native submit of a half-filled or reversed span. -->
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
