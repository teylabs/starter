<script setup lang="ts">
import SectionHeader from '@/components/Kit/Layout/SectionHeader.vue';

// A card for a group of fields: an optional title and description above (a SectionHeader), the content in the default slot.
defineProps<{ title?: string; description?: string }>();
</script>
<template>
    <section class="rounded-xl border bg-card text-card-foreground shadow-xs">
        <div v-if="title" class="border-b px-6 py-4"><SectionHeader :title="title" :description="description" /></div>
        <div class="grid gap-6 p-6"><slot /></div>
    </section>
</template>
