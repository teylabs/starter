<script setup lang="ts">
import PeriodPicker from './PeriodPicker.vue';

// A year as a plain `YYYY`, chosen from a grid of twelve years (PeriodPicker has the keys, the phone sheet and the locked state). A wall value, never converted between zones.
defineOptions({ inheritAttrs: false });
defineProps<{
    name: string;
    label: string;
    id?: string;
    defaultValue?: string;
    min?: string;
    max?: string;
    placeholder?: string;
    description?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
}>();
const emit = defineEmits<{ canonical: [value: string] }>();
</script>
<template>
    <PeriodPicker
        v-bind="{ ...$props, ...$attrs }"
        mode="year"
        @canonical="emit('canonical', $event)"
    />
</template>
