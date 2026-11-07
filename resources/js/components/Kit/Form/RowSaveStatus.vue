<script setup lang="ts">
import { AlertTriangle, Check, LoaderCircle, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import type { RowSaveState } from '@/components/Kit/Form/rowSave';

// One row's autosave state (docs/row-autosave.md), for an editable table's last cell: a polite status, a spinner while saving, and for a stale or failed row an
// alert with its action (`reload` takes the server's copy, `retry` sends the edits again). Idle draws nothing. Words come from `labels`.
const props = withDefaults(
    defineProps<{
        state: RowSaveState;
        reason?: string | null;
        labels?: Partial<
            Record<
                | 'pending'
                | 'saving'
                | 'saved'
                | 'stale'
                | 'failed'
                | 'reload'
                | 'retry',
                string
            >
        >;
    }>(),
    { reason: null, labels: () => ({}) },
);
const emit = defineEmits<{ reload: []; retry: [] }>();
const text = computed(() => ({
    pending: 'Unsaved',
    saving: 'Saving…',
    saved: 'Saved',
    stale: 'Changed elsewhere',
    failed: 'Not saved',
    reload: 'Reload row',
    retry: 'Retry',
    ...props.labels,
}));
const problem = computed(
    () => props.state === 'stale' || props.state === 'failed',
);
</script>
<template>
    <span
        v-if="state !== 'idle'"
        class="text-muted-foreground inline-flex min-w-0 items-center gap-1.5 text-xs"
        :data-row-save="state"
    >
        <span
            :role="problem ? 'alert' : 'status'"
            :aria-live="problem ? 'assertive' : 'polite'"
            class="inline-flex min-w-0 items-center gap-1"
            :class="problem && 'text-destructive-text'"
            :title="reason ?? undefined"
        >
            <LoaderCircle
                v-if="state === 'saving'"
                class="size-3.5 shrink-0 animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />
            <Check
                v-else-if="state === 'saved'"
                class="size-3.5 shrink-0"
                aria-hidden="true"
            />
            <AlertTriangle
                v-else-if="problem"
                class="size-3.5 shrink-0"
                aria-hidden="true"
            />
            <span class="truncate">{{ text[state] }}</span>
        </span>
        <button
            v-if="state === 'stale'"
            type="button"
            class="hover:text-foreground focus-visible:outline-ring inline-flex items-center gap-1 underline underline-offset-2 focus-visible:outline-2"
            @click="emit('reload')"
        >
            <RotateCcw class="size-3.5" aria-hidden="true" />{{ text.reload }}
        </button>
        <button
            v-else-if="state === 'failed'"
            type="button"
            class="hover:text-foreground focus-visible:outline-ring underline underline-offset-2 focus-visible:outline-2"
            @click="emit('retry')"
        >
            {{ text.retry }}
        </button>
    </span>
</template>
