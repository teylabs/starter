<script setup lang="ts">
import { Input as BaseInput } from '@/components/ui/input';

// The text input; `invalid` marks it for the error state, and the other attributes pass through.
defineOptions({ inheritAttrs: false });
defineProps<{ invalid?: boolean }>();
const model = defineModel<string | number>();
</script>
<template>
    <BaseInput v-bind="$attrs" v-model="model" :aria-invalid="invalid || undefined" />
</template>
