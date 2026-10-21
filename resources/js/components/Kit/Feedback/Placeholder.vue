<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Construction } from '@lucide/vue';

/*
 * A page that is not built yet: a dashed panel saying so honestly. "Not built yet", what the page will do, an optional "Planned for {milestone}" (words only: the dot takes
 * the accent token, and no date or version is ever shown) and a few links to go meanwhile. It is a plain region labelled by its heading, with no live role: its content is
 * there when the page loads, so announcing it would be wrong. It takes no input.
 */
defineProps<{ description: string; milestone?: string | null; links?: { label: string; url: string }[] }>();
</script>
<template>
    <section aria-labelledby="placeholder-heading" class="grid max-w-xl justify-items-start gap-3 rounded-xl border border-dashed p-8" data-slot="placeholder">
        <Construction class="size-6 text-muted-foreground" aria-hidden="true" />
        <h2 id="placeholder-heading" class="text-lg font-semibold">Not built yet</h2>
        <p class="text-sm text-muted-foreground">{{ description }}</p>
        <p v-if="milestone" class="flex items-center gap-2 text-sm text-muted-foreground" data-placeholder-milestone>
            <span class="size-2 rounded-full bg-primary" aria-hidden="true" />Planned for {{ milestone }}
        </p>
        <ul v-if="links?.length" class="flex flex-wrap gap-x-4 gap-y-1 pt-1">
            <li v-for="link in links" :key="link.url"><Link :href="link.url" class="inline-flex min-h-11 items-center rounded-md px-1 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ link.label }}</Link></li>
        </ul>
    </section>
</template>
