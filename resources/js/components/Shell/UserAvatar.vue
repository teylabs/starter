<script setup lang="ts">
defineProps<{ initials: string }>();
</script>

<template>
    <span
        aria-hidden="true"
        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800 dark:bg-brand-900 dark:text-brand-100"
    >
        {{ initials }}
    </span>
</template>
