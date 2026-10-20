<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import NoteEditor from './Partials/NoteEditor.vue';
import { Button, Field, FormActions, Section, Textarea } from '@kit/ui';
const props = defineProps<Tey.Kit.Features.Notes.ViewModels.IndexNoteViewModel>();
const form = useForm({ body: '', intent_key: props.create_intent });
function create() { form.post(props.create_url, { preserveScroll: true, onSuccess: () => { form.reset('body'); form.intent_key = props.create_intent; form.defaults(); } }); }
</script>
<template>
    <div class="grid max-w-2xl gap-6">
        <p v-if="!can_write" class="text-sm text-muted-foreground">You can read notes. An owner can create, edit and delete them.</p>
        <form v-if="can_write" @submit.prevent="create">
            <Section title="New note">
                <Field id="new-note" label="Note" :error="form.errors.body">
                    <Textarea id="new-note" v-model="form.body" required maxlength="2000" :invalid="Boolean(form.errors.body)" aria-describedby="new-note-error" />
                </Field>
                <FormActions><Button :disabled="form.processing">{{ form.processing ? 'Creating…' : 'Create note' }}</Button></FormActions>
            </Section>
        </form>
        <p v-if="!notes.length" class="text-sm text-muted-foreground">No notes yet.</p>
        <div class="grid gap-6" aria-live="polite"><NoteEditor v-for="note in notes" :key="`${note.id}-${note.version}`" :note="note" :can_write="can_write" v-bind="actions[note.id]" /></div>
    </div>
</template>
