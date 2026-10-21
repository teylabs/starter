<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import SectionIcon from './SectionIcon.vue';

/*
 * The frame of the settings pages (the nearest folder Layout.vue puts it around a page): a sidebar listing every section the user may open, by group, with the
 * one being shown marked aria-current, and the page in the default slot on the right. On a phone the sidebar is a list at the top. It reads the page's
 * `layout.navigation` (decided on the server, current state included); with none there is no sidebar and the content stands alone.
 */
type Item = { label: string; url: string; current: boolean; icon?: string | null; badge?: string | null };
type Group = { label: string | null; items: Item[] };
const page = usePage<{ layout?: { navigation: Group[] } }>();
</script>
<template>
    <div class="grid gap-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-10" data-settings-frame>
        <nav v-if="page.props.layout?.navigation.length" aria-label="Settings sections" class="md:pt-1" data-settings-sidebar>
            <div v-for="(group, index) in page.props.layout.navigation" :key="index" class="mb-4 last:mb-0">
                <p v-if="group.label" class="mb-1 px-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ group.label }}</p>
                <ul class="flex flex-wrap gap-1 md:grid">
                    <li v-for="item in group.items" :key="item.url">
                        <Link :href="item.url" :aria-current="item.current ? 'page' : undefined" class="flex min-h-9 items-center gap-2 rounded-md px-2 text-sm text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[current=page]:bg-accent aria-[current=page]:font-medium aria-[current=page]:text-foreground">
                            <SectionIcon :name="item.icon ?? undefined" class="size-4" />
                            {{ item.label }}
                            <span v-if="item.badge" class="ml-auto rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground" data-planned-tag>{{ item.badge }}</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>
        <div class="min-w-0"><slot /></div>
    </div>
</template>
