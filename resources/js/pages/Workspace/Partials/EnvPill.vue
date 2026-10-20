<script setup lang="ts">
// Outside production, the environment's name: quiet, never a second nav.
defineProps<{ env: string }>();
</script>
<template>
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-muted-foreground capitalize ring-1 ring-border ring-inset" data-shell-environment>{{ env }}</span>
</template>
