<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ModalLink } from '@inertiaui/modal-vue';
import ActionLabel from '@/components/Kit/Action/ActionLabel.vue';
import {
    opensNewTab,
    rendersAsLink,
    type ActionDescriptor,
} from '@/components/Kit/Action/actionPlacement';

// One visible action as a raw element (ButtonGroup styles its children; the pill path passes classes): dialog → route modal, document → new tab, a GET without a
// confirmation → a link (an Inertia Link, or an <a> in a new tab with `newTab`), anything else a button that emits. `busy` blocks all but a document link.
defineProps<{ action: ActionDescriptor; busy?: boolean; pending?: boolean }>();
// A dialog's ModalLink sends the action's method (a POST that starts work and answers with the modal) and shows its own `loading`; it ignores clicks while loading,
// so a slow modal visit never stacks a second dialog. `pending` is the runner's visit in flight for this action.
const modalMethod = (action: ActionDescriptor) =>
    action.method.toLowerCase() as 'get' | 'post' | 'put' | 'patch' | 'delete';
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
</script>
<template>
    <ModalLink
        v-if="action.intent === 'dialog' && !action.disabled && !busy"
        v-slot="{ loading }"
        :href="action.url"
        :method="modalMethod(action)"
        :navigate="modalMethod(action) === 'get'"
        :data-action="action.key"
        ><span class="contents" :aria-busy="loading || undefined"
            ><ActionLabel :action="action" :pending="loading" /></span
    ></ModalLink>
    <a
        v-else-if="action.intent === 'document' && !action.disabled"
        :href="action.url"
        target="_blank"
        rel="noopener noreferrer"
        :data-action="action.key"
        ><ActionLabel :action="action"
    /></a>
    <a
        v-else-if="rendersAsLink(action) && opensNewTab(action) && !busy"
        :href="action.url"
        target="_blank"
        rel="noopener noreferrer"
        :data-action="action.key"
        ><ActionLabel :action="action"
    /></a>
    <Link
        v-else-if="rendersAsLink(action) && !busy"
        :href="action.url"
        :data-action="action.key"
        ><ActionLabel :action="action"
    /></Link>
    <button
        v-else
        type="button"
        :disabled="busy || action.disabled"
        :title="action.reason ?? undefined"
        :aria-busy="pending || undefined"
        :data-action="action.key"
        @click="emit('action', action)"
    >
        <ActionLabel :action="action" :pending="pending" />
    </button>
</template>
