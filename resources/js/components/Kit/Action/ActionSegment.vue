<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ModalLink } from '@inertiaui/modal-vue';
import ActionLabel from '@/components/Kit/Action/ActionLabel.vue';
import { isNavigation, type ActionDescriptor } from '@/components/Kit/Action/actionPlacement';

// One visible action as a raw element (ButtonGroup styles its children; the pill path passes classes): dialog → route modal, document → new tab, GET →
// Inertia link, anything else a button that emits. `busy` blocks all but a document link.
defineProps<{ action: ActionDescriptor; busy?: boolean }>();
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
</script>
<template>
    <ModalLink v-if="action.intent === 'dialog' && !action.disabled && !busy" :href="action.url" navigate :data-action="action.key"><ActionLabel :action="action" /></ModalLink>
    <a v-else-if="action.intent === 'document' && !action.disabled" :href="action.url" target="_blank" rel="noopener noreferrer" :data-action="action.key"><ActionLabel :action="action" /></a>
    <Link v-else-if="isNavigation(action) && !action.disabled && !busy" :href="action.url" :data-action="action.key"><ActionLabel :action="action" /></Link>
    <button v-else type="button" :disabled="busy || action.disabled" :title="action.reason ?? undefined" :data-action="action.key" @click="emit('action', action)"><ActionLabel :action="action" /></button>
</template>
