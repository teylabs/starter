<script setup lang="ts">
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';
import { type Appearance, useAppearance } from './useAppearance';

// The real appearance setting (light, dark or system), stored per browser and applied before first paint: Reka's ToggleGroup, one item on.
const { appearance, updateAppearance } = useAppearance();
const options: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'system', label: 'System' },
];
// Pressing the item that is on would clear the group: the setting always has a value.
function choose(value: unknown) {
    if (typeof value === 'string' && value !== '') {
        updateAppearance(value as Appearance);
    }
}
</script>
<template>
    <ToggleGroupRoot
        type="single"
        :model-value="appearance"
        aria-label="Appearance"
        class="bg-muted inline-flex rounded-md p-0.5"
        @update:model-value="choose"
    >
        <ToggleGroupItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring data-[state=on]:bg-background data-[state=on]:text-foreground rounded-md px-2 py-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none data-[state=on]:shadow-xs"
        >
            {{ option.label }}
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
