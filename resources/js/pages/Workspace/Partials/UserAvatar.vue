<script setup lang="ts">
defineProps<{ initials: string }>();
</script>
<template>
    <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground">{{ initials }}</span>
</template>
