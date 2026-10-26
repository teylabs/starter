<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { useLocked } from './useLocked';

// The multi-line input, styled as Input is; `invalid` marks it for the error state, and the other attributes pass through.
// Inside a locked LockedForm it is read-only through the context (the LockedForm spec): native `readonly`, sized to its content up to its maximum, then scrolling; the `muted` fill, the value at full contrast and the default arrow pointer. A caller's own `disabled` still wins.
defineOptions({ inheritAttrs: false });
defineProps<{ invalid?: boolean }>();
const model = defineModel<string>();
const attrs = useAttrs();
const lock = useLocked();
const locked = computed(() => lock.locked && (attrs.disabled === undefined || attrs.disabled === false));
</script>
<template>
    <textarea
        v-bind="$attrs"
        v-model="model"
        :readonly="locked || (attrs.readonly as boolean | undefined)"
        :aria-invalid="invalid || undefined"
        :data-locked="locked || undefined"
        :class="[
            'min-h-24 w-full rounded-md border px-3 py-2 text-base transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40',
            locked ? 'max-h-80 field-sizing-content cursor-default border-border bg-muted text-foreground shadow-none' : 'border-input bg-transparent shadow-xs dark:bg-input/30',
        ]"
    />
</template>
