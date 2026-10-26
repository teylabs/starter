<script setup lang="ts">
import { ref } from 'vue';
import { Copy } from '@lucide/vue';
import type { ExplanationNode } from './explanation.generated';
import WorkingTree from './WorkingTree.vue';
import WorkingTechnicalTree from './WorkingTechnicalTree.vue';
defineProps<{ node: ExplanationNode; fallback: string | null }>();
const emit = defineEmits<{ copy: [] }>();
const textarea = ref<HTMLTextAreaElement>();
</script>
<template>
    <WorkingTree :node="node" />
    <div class="mt-4 flex min-w-0 items-start gap-3">
    <details class="min-w-0 flex-1 text-xs text-muted-foreground" data-working-technical>
        <summary class="cursor-pointer rounded-sm focus-visible:outline-2 focus-visible:outline-ring">Technical details</summary>
        <div class="mt-3 min-w-0 wrap-anywhere **:min-w-0 **:max-w-full **:wrap-anywhere"><WorkingTechnicalTree :node="node" /></div>
    </details>
    <button type="button" aria-label="Copy working" class="-my-1 inline-flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring" @click="emit('copy')"><Copy class="size-3.5" aria-hidden="true" />Copy</button>
    </div>
    <div v-if="fallback" class="mt-3 grid gap-2"><label>Working text<textarea ref="textarea" readonly :value="fallback" class="mt-2 min-h-32 w-full rounded-md border border-input bg-background p-3 focus-visible:ring-2 focus-visible:ring-ring" /></label><button type="button" class="min-h-11 rounded-md border border-border px-3 focus-visible:ring-2 focus-visible:ring-ring" @click="textarea?.focus(); textarea?.select()">Select text</button></div>
</template>
