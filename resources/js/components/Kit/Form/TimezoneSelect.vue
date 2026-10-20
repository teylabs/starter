<script setup lang="ts">
import { computed } from 'vue';
import Select from './Select.vue';

// The IANA timezones the browser knows, with the current value kept even when it is not among them.
defineProps<{ id?: string; disabled?: boolean; invalid?: boolean }>();
const model = defineModel<string>();
const zones = computed(() => {
    const known = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];
    const all = new Set(['UTC', ...known, ...(model.value ? [model.value] : [])]);

    return [...all].sort().map((zone) => ({ value: zone, label: zone.replace(/_/g, ' ') }));
});
</script>
<template>
    <Select :id="id" v-model="model" :options="zones" :disabled="disabled" :invalid="invalid" placeholder="Choose a timezone" />
</template>
