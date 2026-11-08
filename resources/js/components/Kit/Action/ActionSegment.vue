<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ModalLink } from '@inertiaui/modal-vue';
import { useId } from 'vue';
import ActionLabel from '@/components/Kit/Action/ActionLabel.vue';
import {
    opensNewTab,
    rendersAsLink,
    type ActionDescriptor,
} from '@/components/Kit/Action/actionPlacement';

// One visible action as a raw element (ButtonGroup styles its children; the pill path passes classes): dialog → route modal, document → new tab, a GET without a
// confirmation → a link (an Inertia Link, or an <a> in a new tab with `newTab`), anything else a button that emits. `busy` blocks all but a document link.
// A `disabled` action stays focusable and hoverable (aria-disabled, not the disabled attribute) so its `reason` shows as a tooltip and is read as its description.
const props = defineProps<{
    action: ActionDescriptor;
    busy?: boolean;
    pending?: boolean;
}>();
const reasonId = `${useId()}-reason`;
function press(action: ActionDescriptor) {
    if (props.busy || action.disabled) return;
    emit('action', action);
}
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
        :aria-label="action.ariaLabel || undefined"
        :data-action="action.key"
        ><span class="contents" :aria-busy="loading || undefined"
            ><ActionLabel :action="action" :pending="loading" /></span
    ></ModalLink>
    <a
        v-else-if="action.intent === 'document' && !action.disabled"
        :href="action.url"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="action.ariaLabel || undefined"
        :data-action="action.key"
        ><ActionLabel :action="action"
    /></a>
    <a
        v-else-if="rendersAsLink(action) && opensNewTab(action) && !busy"
        :href="action.url"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="action.ariaLabel || undefined"
        :data-action="action.key"
        ><ActionLabel :action="action"
    /></a>
    <Link
        v-else-if="rendersAsLink(action) && !busy"
        :href="action.url"
        :aria-label="action.ariaLabel || undefined"
        :data-action="action.key"
        ><ActionLabel :action="action"
    /></Link>
    <button
        v-else
        type="button"
        :disabled="(busy && !pending && !action.disabled) || undefined"
        :aria-disabled="action.disabled || pending || undefined"
        :class="action.disabled && 'cursor-not-allowed opacity-50'"
        :title="(action.disabled && action.reason) || undefined"
        :aria-describedby="
            (action.disabled && action.reason && reasonId) || undefined
        "
        :aria-busy="pending || undefined"
        :aria-label="action.ariaLabel || undefined"
        :data-action="action.key"
        @click="press(action)"
    >
        <ActionLabel :action="action" :pending="pending" /><span
            v-if="action.disabled && action.reason"
            :id="reasonId"
            class="sr-only"
            aria-hidden="true"
            data-action-reason
            >{{ action.reason }}</span
        >
    </button>
</template>
