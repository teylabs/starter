<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button, Field, FormActions, Section, Textarea } from '@kit/ui';
const props = defineProps<{ note: Tey.Kit.Features.Notes.Data.NoteRecord; can_write: boolean; url: string; update_intent: string; delete_intent: string }>();
const form = useForm({ body: props.note.body, expected_version: props.note.version, intent_key: props.update_intent });
const deletion = useForm({ expected_version: props.note.version, intent_key: props.delete_intent });
</script>
<template>
    <article>
        <form v-if="can_write" @submit.prevent="form.put(url, { preserveScroll: true })">
            <Section>
                <Field :id="`note-${note.id}`" :label="`Note ${note.id}`" :error="form.errors.body">
                    <Textarea :id="`note-${note.id}`" v-model="form.body" required maxlength="2000" :invalid="Boolean(form.errors.body)" :aria-describedby="`note-${note.id}-error`" />
                </Field>
                <FormActions>
                    <Button :disabled="form.processing || deletion.processing || !form.isDirty">{{ form.processing ? 'Saving…' : 'Save note' }}</Button>
                    <Button variant="secondary" type="button" :disabled="form.processing || deletion.processing || !form.isDirty" @click="form.reset(); form.clearErrors()">Cancel changes</Button>
                    <Button variant="destructive" type="button" :disabled="form.processing || deletion.processing" @click="deletion.delete(url, { preserveScroll: true })">{{ deletion.processing ? 'Deleting…' : 'Delete note' }}</Button>
                </FormActions>
            </Section>
        </form>
        <Section v-else><p class="whitespace-pre-wrap text-sm">{{ note.body }}</p></Section>
    </article>
</template>
