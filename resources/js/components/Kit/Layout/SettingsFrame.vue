<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import SectionIcon from './SectionIcon.vue';

/*
 * The frame of a settings section's page: a sidebar listing every section the user may open, by group (the server's `settings_nav`, the same data as the
 * landing page's cards), with the one being shown marked aria-current, and the section's content in the default slot on the right. On a phone the sidebar
 * is a list at the top. Outside a section page there is no sidebar and the content stands alone.
 */
type Item = { key: string; label: string; icon: string; url: string; current: boolean };
type Group = { label: string; sections: Item[] };
const page = usePage<{ settings_nav?: { title: string; groups: Group[] } | null }>();
</script>
<template>
    <div class="grid gap-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-10" data-settings-frame>
        <nav v-if="page.props.settings_nav" aria-label="Settings sections" class="md:pt-1" data-settings-sidebar>
            <div v-for="group in page.props.settings_nav.groups" :key="group.label" class="mb-4 last:mb-0">
                <p class="mb-1 px-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ group.label }}</p>
                <ul class="flex flex-wrap gap-1 md:grid">
                    <li v-for="item in group.sections" :key="item.key">
                        <Link :href="item.url" :aria-current="item.current ? 'page' : undefined" class="flex min-h-9 items-center gap-2 rounded-md px-2 text-sm text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[current=page]:bg-accent aria-[current=page]:font-medium aria-[current=page]:text-foreground">
                            <SectionIcon :name="item.icon" class="size-4" />
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>
        <div class="min-w-0 max-w-3xl"><slot /></div>
    </div>
</template>
