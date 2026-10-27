<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button, Field, FileInput, InputCheckbox, InputSelect, Textarea } from '@kit/ui';
import { computed, ref, watch } from 'vue';
import { dot, intentKey } from './colours';

// The quick-add composer: write, pick a colour, attach files one at a time, pin, then Save. Focus returns to the box after a save, ready for the next note. There is no
// keyboard shortcut of its own: key handling belongs inside kit components, and Save is the form's submit button. The app's composer hooks add `options` (links beside Save); a subject, when the composer sits on a record, is what it writes to.
const props = defineProps<{ composer: Tey.Kit.Features.Notes.Data.NoteComposerData; id?: string }>();
const uid = props.id ?? 'note';
const field = ref<{ $el?: HTMLElement } | null>(null);
const chooser = ref<{ clear: () => void } | null>(null);
const picked = ref<File | null>(null);
const form = useForm({ body: '', colour: props.composer.default_colour, pinned: false, files: [] as File[], intent_key: intentKey(), ...(props.composer.subject ? { subject_type: props.composer.subject.type, subject_id: props.composer.subject.id } : {}) });
const options = computed(() => props.composer.colours.map((colour) => ({ value: colour.key, label: colour.label })));
const fileError = computed(() => form.errors.files ?? Object.entries(form.errors).find(([key]) => key.startsWith('files.'))?.[1]);
// FileInput chooses one file: each choice joins the list, and the chooser clears for the next.
watch(picked, (file) => {
    if (!file) return;
    if (form.files.length < props.composer.media.max_files) form.files = [...form.files, file];
    chooser.value?.clear();
});
const focusBox = () => field.value?.$el?.querySelector?.('textarea')?.focus();
function save() {
    if (form.processing || form.body.trim() === '') return;
    form.post(props.composer.submit.url, {
        preserveScroll: true,
        forceFormData: form.files.length > 0,
        onSuccess: () => {
            form.reset('body', 'files', 'pinned');
            form.intent_key = intentKey();
            focusBox();
        },
    });
}
</script>
<template>
    <form class="grid gap-4 rounded-xl border bg-card p-4 shadow-sm" data-note-composer @submit.prevent="save">
        <Field :id="`${uid}-body`" ref="field" label="New note" :error="form.errors.body">
            <Textarea :id="`${uid}-body`" v-model="form.body" rows="3" :placeholder="composer.placeholder" :invalid="!!form.errors.body" :aria-describedby="`${uid}-body-error`" />
        </Field>
        <div class="grid gap-4 sm:grid-cols-2">
            <Field :id="`${uid}-colour`" label="Colour" :error="form.errors.colour">
                <InputSelect :id="`${uid}-colour`" v-model="form.colour" :options="options" :searchable="false" :clearable="false">
                    <template #option="{ option }"><span class="flex items-center gap-2"><span :class="['size-3 rounded-full', dot(String(option.value))]" aria-hidden="true" />{{ option.label }}</span></template>
                </InputSelect>
            </Field>
            <Field :id="`${uid}-file`" label="Attach a file" :description="`Up to ${composer.media.max_files} files.`" :error="fileError">
                <FileInput :id="`${uid}-file`" ref="chooser" v-model="picked" :accept="composer.media.accept.join(',')" :disabled="form.files.length >= composer.media.max_files" />
            </Field>
        </div>
        <ul v-if="form.files.length" class="flex flex-wrap gap-2" aria-label="Files to attach">
            <li v-for="(file, index) in form.files" :key="file.name + index" class="flex items-center gap-2 rounded-md border bg-background py-1 pr-1 pl-2 text-xs">
                <span class="max-w-48 truncate">{{ file.name }}</span>
                <Button type="button" variant="secondary" :aria-label="`Remove ${file.name}`" @click="form.files.splice(index, 1)">Remove</Button>
            </li>
        </ul>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <InputCheckbox :id="`${uid}-pinned`" v-model="form.pinned">Pin to the top</InputCheckbox>
                <a v-for="option in composer.options" :key="option.key" :href="option.url ?? undefined" class="rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted hover:text-foreground" :data-note-option="option.key">{{ option.label }}</a>
            </div>
            <Button type="submit" :disabled="form.processing || form.body.trim() === ''" :aria-busy="form.processing || undefined" data-action="save">{{ form.processing ? 'Saving…' : composer.submit.label }}</Button>
        </div>
    </form>
</template>
