<script setup lang="ts">
defineOptions({ inheritAttrs: false });
import type { ExplanationDocument, ExplanationNode } from './explanation.generated';
import ComputedValueRoot from './ComputedValueRoot.vue';
import Popover from '@/components/Kit/Overlay/Popover.vue';
import WorkingPanel from './WorkingPanel.vue';
withDefaults(defineProps<{ node: ExplanationNode | null; label: string; display?: string | null; open?: boolean; defaultOpen?: boolean; placement?: 'inline' | 'table-row' | 'overlay'; id?: string; unavailableReason?: string; document?: Omit<ExplanationDocument, 'roots'> }>(), { open: undefined });
const emit = defineEmits<{ 'update:open': [value: boolean]; copy: [event: { id: string; status: 'success' | 'failure' }] }>();
</script>
<template>
    <ComputedValueRoot v-slot="state" :node="node" :label="label" :display="display" :open="open" :default-open="defaultOpen" :placement="placement" :id="id" :unavailable-reason="unavailableReason" :document="document" @update:open="emit('update:open', $event)" @copy="emit('copy', $event)">
        <div v-bind="$attrs" class="min-w-0">
            <template v-if="state.available">
                <template v-if="state.inline">
                    <button v-bind="state.triggerAttrs" class="min-h-11 rounded-sm text-left tabular-nums underline decoration-dotted underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring"><slot name="value" :display="state.display">{{ state.display }}</slot><span aria-hidden="true" class="ml-1">{{ state.open ? '▴' : '▾' }}</span></button>
                    <div v-if="state.open" v-bind="state.panelAttrs" class="mt-2 grid gap-3 rounded-lg border border-border bg-card p-4 text-card-foreground"><WorkingPanel :node="node!" :fallback="state.fallback" @copy="state.copyWorking" /><slot name="note" :node="node" /></div>
                </template>
                <Popover v-else :open="state.open" :title="`Working for ${label}`" @update:open="state.setOpen($event)">
                    <template #trigger="surface"><button v-bind="{ ...state.triggerAttrs, onClick: undefined }" class="min-h-11 rounded-sm tabular-nums underline decoration-dotted underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring"><slot name="value" :display="state.display">{{ state.display }}</slot><span aria-hidden="true" class="ml-1">▾</span></button></template>
                    <div v-bind="state.panelAttrs"><WorkingPanel :node="node!" :fallback="state.fallback" @copy="state.copyWorking" /><slot name="note" :node="node" /></div>
                </Popover>
            </template>
            <p v-else><span :aria-label="label" class="tabular-nums">{{ state.display }}</span> <span class="text-sm text-muted-foreground">{{ state.reason }}</span></p>
            <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ state.status }}</p>
        </div>
    </ComputedValueRoot>
</template>
