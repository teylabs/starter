<script setup lang="ts">
import { Pin } from '@lucide/vue';
import NoteComposer from '@/pages/Workspace/Notes/Index/Partials/NoteComposer.vue';
import { tone } from '@/pages/Workspace/Notes/Index/Partials/colours';

// The dashboard's notes card body (the `notes.recent` widget): the composer for someone who may write, then the latest notes, pinned first. "All notes" is the card's action.
type Widget = { title: string; composer: Tey.Kit.Features.Notes.Data.NoteComposerData | null; notes: Tey.Kit.Features.Notes.Data.NoteRecord[] };
defineProps<{ widget: Widget }>();
</script>
<template>
    <div class="grid gap-4" data-widget-notes>
        <NoteComposer v-if="widget.composer" id="widget-note" :composer="widget.composer" />
        <ul v-if="widget.notes.length" class="grid gap-2 sm:grid-cols-2" aria-label="Recent notes">
            <li v-for="note in widget.notes" :key="note.id" :class="['rounded-lg p-3 text-sm', tone(note.colour)]" :data-note="note.id">
                <p class="line-clamp-4 break-words whitespace-pre-line"><Pin v-if="note.pinned" class="mr-1 inline size-3 fill-current" aria-label="Pinned" />{{ note.body }}</p>
                <p class="mt-1 text-xs opacity-80">{{ note.author ?? 'Unknown author' }}</p>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">No notes yet.</p>
    </div>
</template>
