<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FocusPage } from '@kit/ui';
// A settings landing page: the registered sections, grouped and in the registry's order, each with a line saying what it holds.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.SectionsViewModel>();
</script>
<template>
    <FocusPage size="md">
    <p v-if="!groups.length">There is nothing to set up here yet.</p>
    <section v-for="group in groups" :key="group.label" class="mb-8">
        <h2 class="mb-2 text-sm font-medium text-muted-foreground">{{ group.label }}</h2>
        <ul class="grid gap-1">
            <li v-for="section in group.sections" :key="section.url">
                <Link :href="section.url" class="flex flex-col rounded-md px-3 py-2 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                    <span class="text-sm font-medium">{{ section.label }}</span>
                    <span class="text-sm text-muted-foreground">{{ section.description }}</span>
                </Link>
            </li>
        </ul>
    </section>
    </FocusPage>
</template>
