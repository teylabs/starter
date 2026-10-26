<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BlockStack, Page, SectionIcon } from '@kit/ui';
// A settings landing page: the registered sections as cards, grouped and in the registry's order; each card is one link (icon, label, a line saying what it holds).
defineProps<Tey.Kit.Features.SessionUi.ViewModels.SectionsViewModel>();
</script>
<template>
    <Page md>
        <BlockStack class="gap-8">
        <p v-if="!groups.length" class="text-sm text-muted-foreground">There is nothing to set up here yet.</p>
        <BlockStack v-for="group in groups" :key="group.label" as="section" class="gap-3" :aria-label="group.label">
            <h2 class="text-sm font-medium text-muted-foreground">{{ group.label }}</h2>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="section in group.sections" :key="section.url" :data-section="section.key">
                    <Link :href="section.url" class="flex h-full gap-3 rounded-lg border border-border bg-card p-4 text-card-foreground transition-colors hover:border-ring hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-settings-card>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground"><SectionIcon :name="section.icon" /></span>
                        <span class="flex min-w-0 flex-col">
                            <span class="flex items-center gap-2 text-sm font-medium">{{ section.label }}<span v-if="section.badge" class="rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground" data-planned-tag>{{ section.badge }}</span></span>
                            <span class="text-sm text-muted-foreground">{{ section.description }}</span>
                        </span>
                    </Link>
                </li>
            </ul>
        </BlockStack>
        </BlockStack>
    </Page>
</template>
