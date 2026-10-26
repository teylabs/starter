<script setup lang="ts">
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';
import Button from '@/components/Kit/Form/Button.vue';

// Pane switches (a single-choice toggle group, not ARIA tabs or navigation): the host swaps the pane content and owns any persistence. Choosing the current one never deselects.
defineProps<{ tabs: { value: string; label: string }[]; label: string }>();
const selected = defineModel<string>({ required: true });
</script>
<template>
    <ToggleGroupRoot type="single" :model-value="selected" :aria-label="label" class="flex flex-wrap gap-2" data-pane-tabs @update:model-value="value => { if (value) selected = String(value); }">
        <ToggleGroupItem v-for="tab in tabs" :key="tab.value" :value="tab.value" as-child><Button type="button" :variant="selected === tab.value ? 'primary' : 'secondary'">{{ tab.label }}</Button></ToggleGroupItem>
    </ToggleGroupRoot>
</template>
