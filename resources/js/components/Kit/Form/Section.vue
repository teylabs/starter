<script setup lang="ts">
// A card for a group of fields: an optional title and description above, the content in the default slot.
defineProps<{ title?: string; description?: string }>();
</script>
<template>
    <section class="rounded-xl border bg-card text-card-foreground shadow-xs">
        <header v-if="title" class="border-b px-6 py-4">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
        </header>
        <div class="grid gap-6 p-6"><slot /></div>
    </section>
</template>
