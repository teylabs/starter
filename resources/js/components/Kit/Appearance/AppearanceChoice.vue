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
    <ToggleGroupRoot type="single" :model-value="appearance" aria-label="Appearance" class="inline-flex rounded-md bg-muted p-0.5" @update:model-value="choose">
        <ToggleGroupItem v-for="option in options" :key="option.value" :value="option.value" class="rounded-md px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=on]:bg-background data-[state=on]:text-foreground data-[state=on]:shadow-xs">
            {{ option.label }}
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
