<script setup lang="ts">
import { Upload } from '@lucide/vue';
import { ref } from 'vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { captureDrop, capturePaste, checkSelection, describeProblem, setCaptureFiles, sizeHint } from './captureFiles';

/*
 * The one dropzone (BulkDrop.md §9): a dashed, focusable surface that takes files by drop, paste (focus it and paste) or a native browse button, into a visually
 * hidden native file input that stays the form's value. It starts no upload: it emits `files` and the host sends them. `compact` is the overlay-only form for a
 * host that draws its own tile; the host then supplies the labelled picker. `handleDrops` makes this control consume drops; otherwise the host owns them.
 * `busy` only changes the look; `disabled` blocks attaching and removing. `hasFiles` and `empty` are accepted and not yet rendered.
 */
const props = defineProps<{ name: string; label: string; accept: string[]; maxBytes: number; hasFiles: boolean; empty?: object | null; active?: boolean; multiple?: boolean; maxFiles?: number; handleDrops?: boolean; prompt?: string; hint?: string; disabled?: boolean; compact?: boolean; busy?: string }>();
const emit = defineEmits<{ files: [files: File[]] }>();
const input = ref<HTMLInputElement>();
const dragging = ref(0);
const error = ref('');
const filename = ref('');
const hintText = () => props.hint ?? sizeHint(props.maxBytes);
function clear() { error.value = ''; filename.value = ''; input.value?.setCustomValidity(''); }
function selected() {
    const files = Array.from(input.value?.files ?? []);
    // An emptied input (cleared or the last file removed) leaves no stale names or errors.
    if (!files.length) { if (filename.value || error.value) { clear(); emit('files', []); } return; }
    const problem = checkSelection(files, props);
    if (problem) {
        error.value = describeProblem(problem, props);
        filename.value = '';
        if (input.value) { input.value.value = ''; input.value.setCustomValidity(error.value); }
        emit('files', []);
        return;
    }
    clear();
    filename.value = files.map(file => file.name).join(', ');
    emit('files', files);
}
function attach(files: File[]) {
    if (props.disabled || !input.value || !files.length) return;
    setCaptureFiles(input.value, props.multiple ? [...Array.from(input.value.files ?? []), ...files] : files.slice(0, 1));
}
function remove(index: number) {
    if (props.disabled || !input.value) return;
    setCaptureFiles(input.value, Array.from(input.value.files ?? []).filter((_, position) => position !== index));
}
function browse() { if (!props.disabled) input.value?.click(); }
defineExpose({ attach, remove, open: () => input.value?.click() });
function drop(event: DragEvent) {
    dragging.value = 0;
    if (props.handleDrops) { event.stopPropagation(); attach(captureDrop(event)); }
}
function paste(event: ClipboardEvent) { if (props.disabled || event.defaultPrevented) return; attach(capturePaste(event, props.accept)); }
const isFiles = (event: DragEvent) => Boolean(event.dataTransfer?.types.includes('Files'));
function enter(event: DragEvent) { if (!props.disabled && isFiles(event)) { event.preventDefault(); dragging.value++; } }
function leave(event: DragEvent) { if (isFiles(event)) dragging.value = Math.max(0, dragging.value - 1); }
function over(event: DragEvent) { if (isFiles(event)) event.preventDefault(); }
</script>
<template>
    <div :class="compact ? 'relative min-w-0' : ['grid gap-2 rounded-lg border-2 border-dashed px-4 py-5 text-center transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring', dragging > 0 ? 'border-ring bg-accent/40' : 'border-border bg-muted/20', active && 'ring-2 ring-ring/50', disabled && 'opacity-60']" :tabindex="compact ? undefined : 0" :aria-label="compact ? undefined : `Add files to ${label}: drop, paste or browse`" data-dropzone :data-dragging="dragging > 0 || undefined" @paste="paste" @dragenter="enter" @dragleave="leave" @dragover="over" @drop="drop" @keydown.esc="dragging = 0" @dragend="dragging = 0">
        <div v-if="compact && dragging > 0" class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-lg border-2 border-dashed border-ring bg-background/95 p-3 text-sm font-medium" data-dropzone-overlay>Drop to add to {{ label }}</div>
        <div v-if="!compact" class="grid justify-items-center gap-2">
            <Spinner v-if="busy" />
            <Upload v-else class="size-6 text-muted-foreground" aria-hidden="true" />
            <p class="text-sm font-medium" data-dropzone-prompt>
                <template v-if="busy">{{ busy }}</template>
                <template v-else-if="dragging > 0">Drop to add to {{ label }}</template>
                <template v-else-if="disabled && prompt">{{ prompt }}</template>
                <template v-else>Drop, paste or <button type="button" class="rounded-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" :disabled="disabled" data-dropzone-browse @click="browse">browse</button></template>
            </p>
            <p class="text-xs text-muted-foreground" data-dropzone-hint>{{ hintText() }}</p>
        </div>
        <input ref="input" :aria-label="`Files for ${label}`" :name="name" :multiple="multiple" :disabled="disabled" type="file" :accept="accept.join(',')" class="sr-only" tabindex="-1" data-dropzone-input @change="selected" />
        <p v-if="!compact && filename" role="status" class="text-sm break-all">{{ filename }} · ready</p>
        <p v-if="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
        <slot :browse="browse" />
    </div>
</template>
