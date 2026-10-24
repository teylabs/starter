<script setup lang="ts">
import { computed, provide } from 'vue';
import { rowErrorsKey } from './repeaterState';
const props = defineProps<{ errors: Record<string, string> }>();
provide(rowErrorsKey, computed(() => props.errors));
</script>
<template><slot /></template>
