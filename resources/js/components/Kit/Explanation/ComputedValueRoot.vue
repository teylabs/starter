<script setup lang="ts">
import { computed, inject, onBeforeUnmount, ref, useId, watch } from 'vue';
import type { ExplanationDocument, ExplanationNode } from './explanation.generated';
import { scopeKey } from './scope';
import { humanWorkingText } from './working';
const props = withDefaults(defineProps<{ node: ExplanationNode | null; label: string; display?: string | null; open?: boolean; defaultOpen?: boolean; placement?: 'inline' | 'table-row' | 'overlay'; id?: string; unavailableReason?: string; document?: Omit<ExplanationDocument, 'roots'> }>(), { open: undefined, defaultOpen: false, placement: 'inline' });
const emit = defineEmits<{ 'update:open': [value: boolean]; copy: [event: { id: string; status: 'success' | 'failure' }] }>();
const occurrence = useId();
const id = `${props.id ?? 'working'}-${occurrence}`;
const triggerId = `${id}-trigger`, panelId = `${id}-panel`;
const owned = ref(props.defaultOpen);
const available = computed(() => props.node !== null && props.node.schemaVersion === '1' && (!props.document || props.document.schemaVersion === '1'));
const display = computed(() => props.node?.value.display ?? props.display ?? '');
const reason = computed(() => props.node && !available.value ? 'Working unavailable for this version' : props.unavailableReason ?? 'Working unavailable');
const open = computed(() => available.value && (props.open ?? owned.value));
const status = ref('');
const fallback = ref<string | null>(null);
const scope = inject(scopeKey, undefined);
const key = Symbol();
let batchChange = false;
watch(open, value => {
    if (!batchChange) status.value = `Working ${value ? 'shown' : 'hidden'} for ${props.label}`;
    batchChange = false;
});
const containsFocus = () => Boolean(document.getElementById(panelId)?.contains(document.activeElement));
function request(value: boolean, batch = false) {
    if (!available.value) return;
    if (!value && containsFocus()) document.getElementById(triggerId)?.focus();
    batchChange = batch;
    emit('update:open', value);
    if (props.open === undefined) owned.value = value;

}
const close = () => request(false);
const toggle = () => request(!open.value);
function escape(event: KeyboardEvent) { if (event.key === 'Escape' && open.value) { event.preventDefault(); event.stopPropagation(); close(); document.getElementById(triggerId)?.focus(); } }
async function copyWorking() {
    if (!props.node || !available.value) return;
    const text = humanWorkingText(props.node);
    try { await navigator.clipboard.writeText(text); fallback.value = null; status.value = 'Working copied'; emit('copy', { id, status: 'success' }); }
    catch { fallback.value = text; status.value = 'Copy failed. Select the working text to copy.'; emit('copy', { id, status: 'failure' }); }
}
watch(available, value => { if (value) scope?.register(key, { open, request, containsFocus }); else scope?.unregister(key); }, { immediate: true });
onBeforeUnmount(() => scope?.unregister(key));
const inline = computed(() => props.placement !== 'overlay' || Boolean(scope?.intent.value));
const triggerAttrs = computed(() => ({ id: triggerId, type: 'button' as const, 'aria-expanded': open.value, 'aria-controls': panelId, 'aria-label': `${open.value ? 'Hide' : 'Show'} working for ${props.label}: ${display.value}`, onClick: toggle, onKeydown: escape }));
const panelAttrs = { id: panelId, role: 'region', 'aria-labelledby': triggerId, onKeydown: escape };
</script>
<template><slot :node="node" :display="display" :available="available" :reason="reason" :open="open" :inline="inline" :trigger-id="triggerId" :panel-id="panelId" :trigger-attrs="triggerAttrs" :panel-attrs="panelAttrs" :toggle="toggle" :close="close" :set-open="request" :copy-working="copyWorking" :fallback="fallback" :status="status" /></template>
