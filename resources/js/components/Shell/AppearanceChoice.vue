<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';
import Segmented from './Segmented.vue';

/* The real appearance setting: stored per browser and applied before first paint. */
const { appearance, updateAppearance } = useAppearance();

const options: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'system', label: 'System' },
];
</script>

<template>
    <Segmented
        label="Appearance"
        :options="options"
        :model-value="appearance"
        @update:model-value="updateAppearance"
    />
</template>
