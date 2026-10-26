<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { fileSize } from './locked';
import { useLocked } from './useLocked';

// A file chooser: a button that opens the browser's picker and reports the chosen file (`v-model`: File or null). `accept` filters the picker. `current` is the file the
// record already holds (name, size and, when the host gives one, a link to open or download it), shown in place of the picker while locked.
// Inside a locked LockedForm (the LockedForm spec) there is no picker, no choose, replace or remove: the current file shows as its name, size and link, or a muted "No file".
const props = defineProps<{ id: string; accept?: string; disabled?: boolean; autofocus?: boolean; current?: { name: string; size?: number | null; url?: string | null } | null }>();
const model = defineModel<File | null>();
const input = ref<HTMLInputElement | null>(null);
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const shown = computed(() => (model.value ? { name: model.value.name, size: model.value.size, url: null } : props.current ?? null));
onMounted(() => props.autofocus && input.value?.focus());
function chosen(event: Event) {
    model.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}
defineExpose({ clear: () => { model.value = null; if (input.value) input.value.value = ''; } });
</script>
<template>
    <p v-if="locked" :id="id" class="flex min-h-9 flex-wrap items-center gap-x-3 gap-y-1 text-sm" data-file-current>
        <template v-if="shown">
            <span class="text-foreground">{{ shown.name }}</span>
            <span v-if="shown.size != null" class="text-muted-foreground">{{ fileSize(shown.size) }}</span>
            <a v-if="shown.url" :href="shown.url" target="_blank" rel="noopener noreferrer" class="text-primary underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">Open</a>
        </template>
        <span v-else class="text-muted-foreground">No file</span>
    </p>
    <input v-else :id="id" ref="input" type="file" :accept="accept" :disabled="disabled" class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium file:text-secondary-foreground hover:file:bg-secondary/80" @change="chosen" />
</template>
