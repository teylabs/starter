<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Page, ScrollList } from '@kit/ui';
import NoteCard from './Partials/NoteCard.vue';
import NoteComposer from './Partials/NoteComposer.vue';
import { dot } from './Partials/colours';

// The notes wall: the composer (for someone who may write), a colour filter, then the notes, pinned first and newest next, loading more as the viewer scrolls.
const props = defineProps<Tey.Kit.Features.Notes.ViewModels.NotesIndexViewModel>();
const filter = (colour: string | null) => (colour === null ? props.index_url : `${props.index_url}?colour=${encodeURIComponent(colour)}`);
</script>
<template>
    <Page lg :title="title">
        <div class="grid gap-6">
            <NoteComposer v-if="composer" :composer="composer" />
            <nav aria-label="Filter by colour" class="flex flex-wrap items-center gap-2" data-note-filter>
                <Link :href="filter(null)" :aria-current="colour === null ? 'true' : undefined" class="rounded-full border px-3 py-1 text-sm aria-[current]:bg-foreground aria-[current]:text-background" preserve-scroll>All</Link>
                <Link v-for="option in colours" :key="option.key" :href="filter(option.key)" :aria-current="colour === option.key ? 'true' : undefined" class="flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm aria-[current]:bg-foreground aria-[current]:text-background" preserve-scroll>
                    <span :class="['size-2.5 rounded-full', dot(option.key)]" aria-hidden="true" />{{ option.label }}
                </Link>
            </nav>
            <ScrollList :list-key="list" :title="title" style="--scroll-list-height: none">
                <template #row="{ item }"><NoteCard :item="item as unknown as Tey.Kit.Features.Notes.Data.NoteItemData" :colours="colours" /></template>
            </ScrollList>
        </div>
    </Page>
</template>
