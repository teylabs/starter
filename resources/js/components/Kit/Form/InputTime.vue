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
import { parseTime, type Time } from '@internationalized/date';
import { TimeFieldInput, TimeFieldRoot, type TimeValue } from 'reka-ui';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import Field from '@/components/Kit/Form/Field.vue';
import { viewerLocale } from '@/components/Kit/Data/useTimezone';
import { isTime, normalizeTime } from './dateInput';
import { rowErrorsKey } from './repeaterState';
import { useLocked } from './useLocked';

// A time of day as a plain 24-hour `HH:MM`, typed segment by segment (arrows step each segment; the locale decides 12- or 24-hour display). It is a wall time, never
// converted between zones: an opening hour or a reminder time. Inside a locked LockedForm the segments are read-only; the hidden canonical input stays.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        id?: string;
        defaultValue?: string;
        min?: string;
        max?: string;
        step?: number;
        description?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
    }>(),
    { defaultValue: '', step: 1 },
);
const emit = defineEmits<{ canonical: [value: string] }>();
const id = props.id ?? useId();
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const locale = viewerLocale();
const toTime = (value: string | undefined) =>
    value && isTime(value) ? parseTime(normalizeTime(value)) : undefined;
const value = shallowRef<Time | undefined>(toTime(props.defaultValue));
const canonical = computed(() =>
    value.value ? value.value.toString().slice(0, 5) : '',
);
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
async function update(next: TimeValue | undefined) {
    value.value = next
        ? parseTime(
              `${String(next.hour).padStart(2, '0')}:${String(next.minute).padStart(2, '0')}`,
          )
        : undefined;
    emit('canonical', canonical.value);
    await nextTick();
    hidden.value?.dispatchEvent(new Event('input', { bubbles: true }));
}
const reset = () =>
    nextTick(() => {
        value.value = toTime(props.defaultValue);
    });
onMounted(() => root.value?.closest('form')?.addEventListener('reset', reset));
onBeforeUnmount(() =>
    root.value?.closest('form')?.removeEventListener('reset', reset),
);
watch(
    () => props.defaultValue,
    (v) => {
        value.value = toTime(v);
    },
);
const segment =
    'rounded-sm px-0.5 tabular-nums outline-none focus:bg-accent focus:text-accent-foreground data-placeholder:text-muted-foreground';
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
            data-slot="input-time"
            :data-locked="locked || undefined"
        >
            <TimeFieldRoot
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
                <template v-for="item in segments" :key="item.part">
                    <TimeFieldInput
                        v-if="item.part === 'literal'"
                        :part="item.part"
                        class="text-muted-foreground"
                        >{{ item.value }}</TimeFieldInput
                    >
                    <TimeFieldInput v-else :part="item.part" :class="segment">{{
                        item.value
                    }}</TimeFieldInput>
                </template>
            </TimeFieldRoot>
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
