<script setup lang="ts" generic="T extends string">
/* A row of toggle buttons where exactly one is pressed. */
defineProps<{
    label: string;
    options: { value: T; label: string }[];
    modelValue: T;
}>();

defineEmits<{ 'update:modelValue': [value: T] }>();
</script>

<template>
    <div
        role="group"
        :aria-label="label"
        class="inline-flex rounded-md bg-muted p-0.5"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="option.value === modelValue"
            class="rounded-md px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-pressed:bg-background aria-pressed:text-foreground aria-pressed:shadow-xs"
            @click="$emit('update:modelValue', option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
