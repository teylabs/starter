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
        <details
            class="text-muted-foreground min-w-0 flex-1 text-xs"
            data-working-technical
        >
            <summary
                class="focus-visible:outline-ring cursor-pointer rounded-sm focus-visible:outline-2"
            >
                Technical details
            </summary>
            <div
                class="mt-3 min-w-0 wrap-anywhere **:max-w-full **:min-w-0 **:wrap-anywhere"
            >
                <WorkingTechnicalTree :node="node" />
            </div>
        </details>
        <button
            type="button"
            aria-label="Copy working"
            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring -my-1 inline-flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1 text-xs focus-visible:ring-2"
            @click="emit('copy')"
        >
            <Copy class="size-3.5" aria-hidden="true" />Copy
        </button>
    </div>
    <div v-if="fallback" class="mt-3 grid gap-2">
        <label
            >Working text<textarea
                ref="textarea"
                readonly
                :value="fallback"
                class="border-input bg-background focus-visible:ring-ring mt-2 min-h-32 w-full rounded-md border p-3 focus-visible:ring-2"
            /></label
        ><button
            type="button"
            class="border-border focus-visible:ring-ring min-h-11 rounded-md border px-3 focus-visible:ring-2"
            @click="
                textarea?.focus();
                textarea?.select();
            "
        >
            Select text
        </button>
    </div>
</template>
