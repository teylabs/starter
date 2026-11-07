<script setup lang="ts">
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';

// Pane switches (a single-choice toggle group, not ARIA tabs or navigation): the host swaps the pane content and owns any persistence. Choosing the current one never deselects.
// Styled as SegmentedToggle: the pressed pane is a neutral raised surface in a muted track, never primary-filled, so it doesn't read as a second primary action.
defineProps<{ tabs: { value: string; label: string }[]; label: string }>();
const selected = defineModel<string>({ required: true });
</script>
<template>
    <ToggleGroupRoot
        type="single"
        :model-value="selected"
        :aria-label="label"
        class="bg-muted inline-flex flex-wrap items-center gap-0.5 rounded-lg p-0.5"
        data-pane-tabs
        @update:model-value="
            (value) => {
                if (value) selected = String(value);
            }
        "
    >
        <ToggleGroupItem
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
            :class="[
                'text-foreground focus-visible:outline-ring inline-flex h-8 items-center justify-center rounded-md border px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2',
                selected === tab.value
                    ? 'border-muted-foreground bg-background shadow-sm'
                    : 'hover:bg-background/60 border-transparent bg-transparent',
            ]"
            >{{ tab.label }}</ToggleGroupItem
        >
    </ToggleGroupRoot>
</template>
