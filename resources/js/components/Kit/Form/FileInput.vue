<script setup lang="ts">
import { onMounted, ref } from 'vue';

// A file chooser: a button that opens the browser's picker and reports the chosen file (`v-model`: File or null). `accept` filters the picker.
const props = defineProps<{ id: string; accept?: string; disabled?: boolean; autofocus?: boolean }>();
const model = defineModel<File | null>();
const input = ref<HTMLInputElement | null>(null);
onMounted(() => props.autofocus && input.value?.focus());
function chosen(event: Event) {
    model.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}
defineExpose({ clear: () => { model.value = null; if (input.value) input.value.value = ''; } });
</script>
<template>
    <input :id="id" ref="input" type="file" :accept="accept" :disabled="disabled" class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium file:text-secondary-foreground hover:file:bg-secondary/80" @change="chosen" />
</template>
