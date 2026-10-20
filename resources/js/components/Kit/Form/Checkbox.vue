<script setup lang="ts">
import { Checkbox as BaseCheckbox } from '@/components/ui/checkbox';

// A checkbox with its label beside it, in the default slot.
defineProps<{ id: string; disabled?: boolean }>();
const model = defineModel<boolean>();
</script>
<template>
    <div class="flex items-center gap-2">
        <BaseCheckbox :id="id" v-model="model" :disabled="disabled" />
        <label :for="id" class="text-sm leading-none"><slot /></label>
    </div>
</template>
