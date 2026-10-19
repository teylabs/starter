<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import NoteEditor from './Partials/NoteEditor.vue';
const props = defineProps<Tey.Kit.Features.Notes.ViewModels.IndexNoteViewModel>();
const form = useForm({ body: '', intent_key: props.create_intent });
function create() { form.post(props.create_url, { preserveScroll: true, onSuccess: () => { form.reset('body'); form.intent_key = props.create_intent; form.defaults(); } }); }
</script>
<template>
    <p v-if="!can_write">You can read notes. An owner can create, edit and delete them.</p>
    <form v-if="can_write" @submit.prevent="create">
        <label for="new-note">New note</label><textarea id="new-note" v-model="form.body" required maxlength="2000" :aria-invalid="Boolean(form.errors.body)" aria-describedby="new-note-error" /><p id="new-note-error" role="alert">{{ form.errors.body }}</p><button type="submit" :disabled="form.processing">{{ form.processing ? 'Creating…' : 'Create note' }}</button>
    </form>
    <p v-if="!notes.length">No notes yet.</p>
    <div aria-live="polite"><NoteEditor v-for="note in notes" :key="`${note.id}-${note.version}`" :note="note" :can_write="can_write" v-bind="actions[note.id]" /></div>
</template>
