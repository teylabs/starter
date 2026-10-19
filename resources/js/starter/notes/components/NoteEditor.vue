<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{ note: App.Starter.Notes.Data.NoteRecord; can_write: boolean; url: string; update_intent: string; delete_intent: string }>();
const form = useForm({ body: props.note.body, expected_version: props.note.version, intent_key: props.update_intent });
const deletion = useForm({ expected_version: props.note.version, intent_key: props.delete_intent });
</script>
<template><article class="note">
    <form v-if="can_write" @submit.prevent="form.put(url, { preserveScroll: true })">
        <label :for="`note-${note.id}`">Note {{ note.id }}</label><textarea :id="`note-${note.id}`" v-model="form.body" required maxlength="2000" :aria-invalid="Boolean(form.errors.body)" :aria-describedby="`note-error-${note.id}`" /><p :id="`note-error-${note.id}`" class="error" role="alert">{{ form.errors.body }}</p>
        <div class="actions"><button type="submit" :disabled="form.processing || deletion.processing || !form.isDirty">{{ form.processing ? 'Saving…' : 'Save note' }}</button><button type="button" :disabled="form.processing || deletion.processing || !form.isDirty" @click="form.reset(); form.clearErrors()">Cancel changes</button><button type="button" :disabled="form.processing || deletion.processing" @click="deletion.delete(url, { preserveScroll: true })">{{ deletion.processing ? 'Deleting…' : 'Delete note' }}</button></div>
    </form>
    <p v-else class="note-body">{{ note.body }}</p>
</article></template>
