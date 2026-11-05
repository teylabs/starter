<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { Button, Field, FileInput, FormActions, Input, InputCheckbox, InputSelect, Textarea } from '@kit/ui';
import { computed, nextTick, ref, watch } from 'vue';
import { dot, intentKey } from './colours';

// The quick-add composer: Inertia's <Form> (uncontrolled; every control submits a named value) ending in FormActions. Write, pick a colour, attach files one at a time,
// pin, then Add note. After a save the fields reset, a fresh intent key is ready, and focus returns to the box for the next note. The app's composer hooks add `options`
// (links beside the form); a subject, when the composer sits on a record, is what it writes to.
const props = defineProps<{ composer: Tey.Kit.Features.Notes.Data.NoteComposerData; id?: string }>();
const uid = props.id ?? 'note';
const field = ref<{ $el?: HTMLElement } | null>(null);
const chooser = ref<{ clear: () => void } | null>(null);
const picked = ref<File | null>(null);
// FileInput chooses one file and submits nothing itself: the chosen files join the request through the form's transform, as does the intent key, which
// changes after each save and so must not be a field (a field that changes reads as an unsaved edit).
const files = ref<File[]>([]);
const key = ref(intentKey());
const options = computed(() => props.composer.colours.map((colour) => ({ value: colour.key, label: colour.label })));
watch(picked, (file) => {
    if (!file) return;
    if (files.value.length < props.composer.media.max_files) files.value = [...files.value, file];
    chooser.value?.clear();
});
const withFiles = (data: Record<string, FormDataConvertible>): Record<string, FormDataConvertible> => ({ ...data, intent_key: key.value, ...(files.value.length ? { files: files.value } : {}) });
const fileError = (errors: Record<string, string>) => errors.files ?? Object.entries(errors).find(([name]) => name.startsWith('files.'))?.[1];
async function saved() {
    files.value = [];
    key.value = intentKey();
    await nextTick();
    field.value?.$el?.querySelector?.('textarea')?.focus();
}
</script>
<template>
    <Form v-slot="{ errors }" :action="composer.submit.url" method="post" :transform="withFiles" :options="{ preserveScroll: true }" :reset-on-success="['body', 'pinned']" set-defaults-on-success class="grid gap-4 rounded-xl border bg-card p-4 shadow-sm" data-note-composer @success="saved">
        <template v-if="composer.subject">
            <Input type="hidden" name="subject_type" :model-value="composer.subject.type" />
            <Input type="hidden" name="subject_id" :model-value="String(composer.subject.id)" />
        </template>
        <Field :id="`${uid}-body`" ref="field" name="body" label="New note" :error="errors.body">
            <Textarea :id="`${uid}-body`" name="body" rows="3" :placeholder="composer.placeholder" required maxlength="5000" :invalid="!!errors.body" :aria-describedby="`${uid}-body-error`" />
        </Field>
        <div class="grid gap-4 sm:grid-cols-2">
            <Field :id="`${uid}-colour`" name="colour" label="Colour" :error="errors.colour">
                <InputSelect :id="`${uid}-colour`" name="colour" :model-value="composer.default_colour" :options="options" :searchable="false" :clearable="false">
                    <template #option="{ option }"><span class="flex items-center gap-2"><span :class="['size-3 rounded-full', dot(String(option.value))]" aria-hidden="true" />{{ option.label }}</span></template>
                </InputSelect>
            </Field>
            <Field :id="`${uid}-file`" label="Attach a file" :description="`Up to ${composer.media.max_files} files.`" :error="fileError(errors)">
                <FileInput :id="`${uid}-file`" ref="chooser" v-model="picked" :accept="composer.media.accept.join(',')" :disabled="files.length >= composer.media.max_files" />
            </Field>
        </div>
        <ul v-if="files.length" class="flex flex-wrap gap-2" aria-label="Files to attach">
            <li v-for="(file, index) in files" :key="file.name + index" class="flex items-center gap-2 rounded-md border bg-background py-1 pr-1 pl-2 text-xs">
                <span class="max-w-48 truncate">{{ file.name }}</span>
                <Button type="button" variant="secondary" :aria-label="`Remove ${file.name}`" @click="files.splice(index, 1)">Remove</Button>
            </li>
        </ul>
        <div class="flex flex-wrap items-center gap-3">
            <InputCheckbox :id="`${uid}-pinned`" name="pinned" value="1">Pin to the top</InputCheckbox>
            <a v-for="option in composer.options" :key="option.key" :href="option.url ?? undefined" class="rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted hover:text-foreground" :data-note-option="option.key">{{ option.label }}</a>
        </div>
        <FormActions :submit-label="composer.submit.label" busy-label="Saving…" saved-label="Added" :sticky="false" :cancel="false" :warn-on-leave="false" />
    </Form>
</template>
