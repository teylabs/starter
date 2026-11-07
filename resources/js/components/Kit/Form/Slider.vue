<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue';
import { useLocked } from './useLocked';

/*
 * A number on a range: a native <input type="range">, so the browser gives it the slider role, its value, the arrow keys, Page Up and Down, Home and End, and
 * drag. `v-model` is the number (`min` when unset). It emits `input` with the value live while it's dragged or stepped, and `change` once on release (a commit,
 * such as saving). `valueText` turns the value into `aria-valuetext` ("40%", "£25"), read instead of the bare number. `showValue` shows the value beside it in
 * an <output> linked to the input by `for`, not a live region (it would announce every step); `valueText` formats it too.
 *
 * In a Field: `id` is the Field's, and the input is described by the Field's description and error (`<id>-description <id>-error`), after any
 * `aria-describedby` the caller gives. Caller attributes (`name`, `data-*`, `aria-*`, `class`) land on the input, not a wrapper. `invalid` sets `aria-invalid`.
 * Inside a locked LockedForm it is read-only through the context (the LockedForm spec): still focusable and announced, `aria-readonly`, the default cursor,
 * and a key or drag puts the value back. A caller's own `disabled` still wins.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        id?: string;
        min?: number;
        max?: number;
        step?: number;
        disabled?: boolean;
        invalid?: boolean;
        valueText?: (value: number) => string;
        showValue?: boolean;
    }>(),
    {
        id: undefined,
        min: 0,
        max: 100,
        step: 1,
        disabled: false,
        invalid: false,
        valueText: undefined,
        showValue: false,
    },
);
const emit = defineEmits<{ input: [value: number]; change: [value: number] }>();
const model = defineModel<number>();
const attrs = useAttrs();
const lock = useLocked();
const fallback = useId();
const inputId = computed(() => props.id ?? `slider-${fallback}`);
const locked = computed(() => lock.locked && !props.disabled);
const value = computed(() => model.value ?? props.min);
const text = computed(() =>
    props.valueText ? props.valueText(value.value) : String(value.value),
);
const describedBy = computed(
    () =>
        [
            ...new Set(
                [
                    ...String(attrs['aria-describedby'] ?? '').split(/\s+/),
                    ...(props.id
                        ? [`${props.id}-description`, `${props.id}-error`]
                        : []),
                ].filter(Boolean),
            ),
        ].join(' ') || undefined,
);
function read(event: Event): number | null {
    const input = event.target as HTMLInputElement;
    if (locked.value) {
        input.value = String(value.value);
        return null;
    }

    return Number(input.value);
}
function onInput(event: Event): void {
    const next = read(event);
    if (next === null) return;
    model.value = next;
    emit('input', next);
}
function onChange(event: Event): void {
    const next = read(event);
    if (next !== null) emit('change', next);
}
</script>
<template>
    <div class="flex items-center gap-3" data-slider>
        <input
            v-bind="$attrs"
            :id="inputId"
            type="range"
            :min="min"
            :max="max"
            :step="step"
            :value="value"
            :disabled="disabled"
            :aria-valuetext="props.valueText !== undefined ? text : undefined"
            :aria-invalid="invalid || undefined"
            :aria-readonly="locked || undefined"
            :aria-describedby="describedBy"
            :data-locked="locked || undefined"
            :class="[
                'accent-primary focus-visible:outline-ring h-6 min-w-0 flex-1 rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                invalid ? 'accent-destructive' : '',
                locked ? 'cursor-default' : 'cursor-pointer',
            ]"
            @input="onInput"
            @change="onChange"
        />
        <output
            v-if="showValue"
            :for="inputId"
            aria-live="off"
            class="text-foreground min-w-12 shrink-0 text-right text-sm tabular-nums"
            data-slider-value
            >{{ text }}</output
        >
    </div>
</template>
