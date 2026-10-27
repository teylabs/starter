<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import FloatingToolbar from '@/components/Kit/Layout/FloatingToolbar.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';

/*
 * The bar for the selected rows: a FloatingToolbar pinned to the viewport's foot (so it registers with useStickyBars and the Toaster sits above it) with the count,
 * the server's bulk actions and Clear. It shows only while something is selected. An action posts `ids[]` to its URL (after its `confirm`, if any) and the
 * selection clears once the visit succeeds; the server resolves the ids itself (TableSelection) and refuses the whole request if any is not available.
 * `intercept` names actions the page performs itself (it listens to `act`).
 */
const props = withDefaults(defineProps<{ ids: (string | number)[]; actions: ActionDescriptor[]; intercept?: string[]; label?: string }>(), { intercept: () => [], label: 'Selected rows' });
const emit = defineEmits<{ clear: []; act: [action: ActionDescriptor, ids: (string | number)[]] }>();
const clear: ActionDescriptor = { key: 'clear-selection', label: 'Clear', url: '', method: 'post' };
const shown = computed(() => [...props.actions, clear]);
const busy = ref(false);
const asking = ref<ActionDescriptor | null>(null);
// Kept apart from `asking`: the dialog closes itself (clearing it) around the click that confirms it.
let pending: ActionDescriptor | null = null;
const confirm = ref<ActionDescriptor['confirm']>(null);
const asked = computed({ get: () => asking.value !== null, set: (open: boolean) => { if (!open) asking.value = null; } });

function perform(action: ActionDescriptor) {
    if (action.key === clear.key) return emit('clear');
    if (props.intercept.includes(action.key ?? '')) return emit('act', action, props.ids);
    if (action.confirm) {
        pending = action;
        confirm.value = action.confirm;
        setTimeout(() => (asking.value = action), 0);
        return;
    }
    run(action);
}
function run(action: ActionDescriptor) {
    router.visit(action.url, {
        method: action.method as 'post' | 'put' | 'patch' | 'delete',
        data: { ids: props.ids },
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
        onSuccess: () => emit('clear'),
    });
}
function confirmed() {
    if (pending) run(pending);
    pending = null;
    asking.value = null;
}
</script>
<template>
    <FloatingToolbar v-if="ids.length" mode="float" :label="label" data-bulk-bar>
        <span class="shrink-0 text-sm font-medium" aria-live="polite" data-bulk-count>{{ ids.length }} selected</span>
        <ActionBar :actions="shown" :busy="busy" :dialog-open="asked" @action="perform" />
    </FloatingToolbar>
    <Dialog v-if="confirm" variant="danger" v-model:open="asked" :title="confirm.title" :description="confirm.description" :confirm-label="confirm.label" @confirm="confirmed" />
</template>
