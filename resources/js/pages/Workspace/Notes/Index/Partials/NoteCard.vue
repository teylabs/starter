<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { FileText, Pin } from '@lucide/vue';
import { Button, Dropdown, DropdownConfirm, DropdownItem, Field, InputSelect, Textarea } from '@kit/ui';
import { computed, nextTick, ref } from 'vue';
import { dot, intentKey, tone } from './colours';

// One note on the wall: its body, author and time, the record it is about, its attachments, and, for someone who may change it (their own, or a moderator), Edit, Pin and
// Delete (with a confirmation). Every change sends the version the card shows: a note someone changed meanwhile is refused as out of date, never overwritten.
const props = defineProps<{ item: Tey.Kit.Features.Notes.Data.NoteItemData; colours: { key: string; label: string }[] }>();
const note = computed(() => props.item.note);
const options = computed(() => props.colours.map((colour) => ({ value: colour.key, label: colour.label })));
const editing = ref(false);
const editor = ref<{ $el?: HTMLElement } | null>(null);
const form = useForm({ body: '', colour: '', expected_version: 0, intent_key: '' });
const when = (at: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(at));
async function edit() {
    form.defaults({ body: note.value.body, colour: note.value.colour, expected_version: note.value.version, intent_key: intentKey() });
    form.reset();
    editing.value = true;
    await nextTick();
    (editor.value?.$el?.querySelector('textarea') ?? editor.value?.$el)?.focus?.();
}
function save() {
    if (!props.item.update || form.processing) return;
    form.put(props.item.update.url, { preserveScroll: true, onSuccess: () => (editing.value = false) });
}
function pin() {
    if (!props.item.update) return;
    router.put(props.item.update.url, { pinned: !note.value.pinned, expected_version: note.value.version, intent_key: intentKey() }, { preserveScroll: true });
}
const remove = () => new Promise<void>((resolve) => router.delete(props.item.delete!.url, { data: { expected_version: note.value.version, intent_key: intentKey() }, preserveScroll: true, onFinish: () => resolve() }));
</script>
<template>
    <article :class="['grid gap-3 rounded-xl p-4 shadow-sm', tone(note.colour)]" :data-note="note.id" :data-colour="note.colour" :aria-labelledby="`note-${note.id}-by`">
        <header class="flex items-start justify-between gap-3">
            <p :id="`note-${note.id}-by`" class="text-xs opacity-80">
                <Pin v-if="note.pinned" class="mr-1 inline size-3 fill-current" aria-label="Pinned" />
                <span class="font-medium">{{ note.author ?? 'Unknown author' }}</span> · <time :datetime="note.created_at">{{ when(note.created_at) }}</time>
            </p>
            <Dropdown v-if="item.update || item.delete" horizontal :aria-label="`Actions for note ${note.id}`" data-note-menu>
                <DropdownItem v-if="item.update" as="button" data-note-edit @click="edit">Edit</DropdownItem>
                <DropdownItem v-if="item.update" as="button" data-note-pin @click="pin">{{ note.pinned ? 'Unpin' : 'Pin' }}</DropdownItem>
                <DropdownConfirm v-if="item.delete" :on-confirmed="remove" variant="danger" :confirm-label="item.delete.confirm?.label ?? 'Delete'" data-note-delete>
                    {{ item.delete.label }}
                    <template #title>{{ item.delete.confirm?.title ?? 'Delete this note?' }}</template>
                    <template #confirmation><p>{{ item.delete.confirm?.description }}</p></template>
                </DropdownConfirm>
            </Dropdown>
        </header>
        <form v-if="editing" class="grid gap-3" data-note-editor @submit.prevent="save">
            <Field :id="`note-${note.id}-body`" ref="editor" label="Note" :error="form.errors.body">
                <Textarea :id="`note-${note.id}-body`" v-model="form.body" rows="3" :invalid="!!form.errors.body" :aria-describedby="`note-${note.id}-body-error`" />
            </Field>
            <div class="flex flex-wrap items-end justify-between gap-2">
                <Field :id="`note-${note.id}-colour`" label="Colour" :error="form.errors.colour">
                    <InputSelect :id="`note-${note.id}-colour`" v-model="form.colour" :options="options" :searchable="false" :clearable="false">
                        <template #option="{ option }"><span class="flex items-center gap-2"><span :class="['size-3 rounded-full', dot(String(option.value))]" aria-hidden="true" />{{ option.label }}</span></template>
                    </InputSelect>
                </Field>
                <div class="flex gap-2">
                    <Button type="button" variant="secondary" @click="editing = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing" data-action="save">{{ form.processing ? 'Saving…' : 'Save' }}</Button>
                </div>
            </div>
        </form>
        <p v-else class="text-sm break-words whitespace-pre-line">{{ note.body }}</p>
        <a v-if="note.subject" :href="note.subject.url ?? undefined" class="w-fit rounded-full bg-background/60 px-2 py-0.5 text-xs font-medium hover:underline" data-note-subject>{{ note.subject.label }}</a>
        <ul v-if="note.attachments.length" class="flex flex-wrap gap-2" aria-label="Attachments">
            <li v-for="file in note.attachments" :key="file.id">
                <a :href="file.url" class="flex items-center gap-2 rounded-md bg-background/60 p-1 pr-2 text-xs hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" :data-note-attachment="file.id">
                    <img v-if="file.thumb_url" :src="file.thumb_url" alt="" class="size-10 rounded object-cover" loading="lazy" />
                    <FileText v-else class="size-5" aria-hidden="true" />
                    <span class="max-w-40 truncate">{{ file.name }}</span>
                </a>
            </li>
        </ul>
    </article>
</template>
