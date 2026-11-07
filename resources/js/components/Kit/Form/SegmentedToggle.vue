<script setup lang="ts">
import { computed, ref, type Component } from 'vue';

export type SegmentedToggleOption = {
    value: string;
    label: string;
    icon?: Component;
    disabled?: boolean;
};

/*
 * A neutral segmented toggle for view switches ("Slides | Outline", grid or list): one choice of a few, shown side by side. Its selected segment is a raised
 * surface (bg-background with a border and a shadow, inside a muted track), never brand-filled, so it doesn't read as a second primary action.
 * A `role="radiogroup"` of `role="radio"` buttons, named by `label` (its aria-label): one tab stop (a roving tabindex on the selected segment), the arrow keys move and select
 * (wrapping), Home and End go to the first and last, and a disabled option is skipped. `v-model` is the selected value. Each segment carries `data-value`.
 * The selected border is `--muted-foreground`, which keeps 3:1 against the track and the segment in light and dark (tests/js/segmented-toggle.test.mjs).
 */
const props = withDefaults(
    defineProps<{
        options: SegmentedToggleOption[];
        label: string;
        size?: 'default' | 'sm';
    }>(),
    { size: 'default' },
);
const model = defineModel<string>({ required: true });
const segments = ref<HTMLElement[]>([]);
const enabled = computed(() =>
    props.options
        .map((option, index) => (option.disabled ? -1 : index))
        .filter((index) => index >= 0),
);
// The tab stop: the selected segment, or the first enabled one when nothing enabled is selected.
const stop = computed(() => {
    const index = props.options.findIndex(
        (option) => option.value === model.value && !option.disabled,
    );
    return index >= 0 ? index : (enabled.value[0] ?? -1);
});
function choose(index: number): void {
    const option = props.options[index];
    if (!option || option.disabled) return;
    model.value = option.value;
    segments.value[index]?.focus();
}
function key(event: KeyboardEvent, index: number): void {
    const list = enabled.value;
    if (!list.length) return;
    const at = list.indexOf(index);
    const next = {
        ArrowRight: list[(at + 1) % list.length],
        ArrowDown: list[(at + 1) % list.length],
        ArrowLeft: list[(at - 1 + list.length) % list.length],
        ArrowUp: list[(at - 1 + list.length) % list.length],
        Home: list[0],
        End: list[list.length - 1],
    }[event.key];
    if (next === undefined) return;
    event.preventDefault();
    choose(next);
}
</script>
<template>
    <div
        role="radiogroup"
        :aria-label="label"
        class="bg-muted inline-flex items-center gap-0.5 rounded-lg p-0.5"
        data-segmented-toggle
    >
        <button
            v-for="(option, index) in options"
            :key="option.value"
            :ref="
                (el: unknown) => {
                    if (el) segments[index] = el as HTMLElement;
                }
            "
            type="button"
            role="radio"
            :aria-checked="model === option.value ? 'true' : 'false'"
            :aria-disabled="option.disabled ? 'true' : undefined"
            :disabled="option.disabled"
            :tabindex="index === stop ? 0 : -1"
            :data-value="option.value"
            :data-selected="model === option.value ? '' : undefined"
            :class="[
                'text-foreground focus-visible:outline-ring inline-flex items-center justify-center gap-1.5 rounded-md border font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                size === 'sm' ? 'h-7 px-2.5 text-xs' : 'h-8 px-3 text-sm',
                model === option.value
                    ? 'border-muted-foreground bg-background shadow-sm'
                    : 'hover:bg-background/60 border-transparent bg-transparent',
            ]"
            @click="choose(index)"
            @keydown="key($event, index)"
        >
            <component
                :is="option.icon"
                v-if="option.icon"
                aria-hidden="true"
                :class="size === 'sm' ? 'size-3.5' : 'size-4'"
            />
            <span>{{ option.label }}</span>
        </button>
    </div>
</template>
