<script setup lang="ts">
import SectionHeader from '@/components/Kit/Layout/SectionHeader.vue';
import { provideCard } from '@/components/Kit/Surface/surface';

// A card for a group of fields: an optional title and description above (a SectionHeader), the content in the default slot.
defineProps<{ title?: string; description?: string }>();
// A card on any ground: nothing inside draws a ground card.
provideCard();
</script>
<template>
    <section class="bg-card text-card-foreground rounded-xl border shadow-xs">
        <div v-if="title" class="border-b px-6 py-4">
            <SectionHeader :title="title" :description="description" />
        </div>
        <div class="grid gap-6 p-6"><slot /></div>
    </section>
</template>
