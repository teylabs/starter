<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { useFieldAria } from './fieldAria';
import { useLocked } from './useLocked';

// The multi-line input, styled as Input is; `invalid` marks it for the error state, and the other attributes pass through.
// Inside a locked LockedForm it is read-only through the context (the LockedForm spec): native `readonly`, sized to its content up to its maximum, then scrolling; the `muted` fill, the value at full contrast and the default arrow pointer. A caller's own `disabled` still wins.
defineOptions({ inheritAttrs: false });
const props = defineProps<{ invalid?: boolean }>();
const model = defineModel<string>();
const attrs = useAttrs();
const lock = useLocked();
// Inside a Field: described only by what it renders, and invalid while it has an error.
const aria = useFieldAria(attrs, () => props.invalid);
const locked = computed(
    () =>
        lock.locked &&
        (attrs.disabled === undefined || attrs.disabled === false),
);
</script>
<template>
    <textarea
        v-bind="$attrs"
        v-model="model"
        :readonly="locked || (attrs.readonly as boolean | undefined)"
        :aria-describedby="aria['aria-describedby']"
        :aria-invalid="aria['aria-invalid']"
        :data-locked="locked || undefined"
        :class="[
            'placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 min-h-24 w-full rounded-md border px-3 py-2 text-base transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            locked
                ? 'border-border bg-muted text-foreground field-sizing-content max-h-80 cursor-default shadow-none'
                : 'border-input dark:bg-input/30 bg-transparent shadow-xs',
        ]"
    />
</template>
