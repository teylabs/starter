<script setup lang="ts">
import { GripVertical } from '@lucide/vue';

/**
 * A row's drag grip: a real button (it takes focus), so a row may hold links and other buttons beside it without nesting interactives. Pointer drag starts
 * here (useSortable's handle); while it has focus, ↑/↓ (or ←/→) move the row one place and Home/End to the ends. `label` names the row it moves.
 */
const props = withDefaults(
    defineProps<{ label: string; disabled?: boolean }>(),
    { disabled: false },
);
const emit = defineEmits<{ keydown: [event: KeyboardEvent] }>();
</script>
<template>
    <button
        type="button"
        :aria-label="`Reorder ${props.label}`"
        aria-roledescription="drag handle"
        :aria-disabled="props.disabled || undefined"
        title="Drag, or use the arrow keys, to reorder"
        class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring inline-flex size-8 shrink-0 cursor-grab touch-none items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none active:cursor-grabbing aria-disabled:pointer-events-none aria-disabled:opacity-30"
        data-sortable-handle
        @keydown="(event) => emit('keydown', event)"
    >
        <GripVertical class="size-4" aria-hidden="true" />
    </button>
</template>
