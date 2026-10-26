<script setup lang="ts">
import { ModalLink } from '@inertiaui/modal-vue';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import ActionLabel from '@/components/Kit/Action/ActionLabel.vue';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import { actionIconName, type ActionDescriptor } from '@/components/Kit/Action/actionPlacement';

// The menu rows for a list of actions, inside a DropdownMenuContent: dialog → route modal, document → new tab, everything else emitted after the menu closes.
defineProps<{ actions: ActionDescriptor[]; busy?: boolean }>();
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
const menuIcon = (action: ActionDescriptor) => actionIcons[actionIconName(action, true) ?? ''];
// The menu closes and gives focus back first; opened in the same tick, the menu's own dismissal would swallow the host's confirmation.
const select = (action: ActionDescriptor) => setTimeout(() => emit('action', action), 0);
</script>
<template>
    <template v-for="action in actions" :key="action.key ?? action.url">
        <DropdownMenuItem v-if="action.intent === 'dialog' && !action.disabled" as-child :disabled="busy" :data-action="action.key">
            <ModalLink :href="action.url" navigate><component :is="menuIcon(action)" v-if="menuIcon(action)" class="size-4" aria-hidden="true" /><ActionLabel :action="action" menu /></ModalLink>
        </DropdownMenuItem>
        <DropdownMenuItem v-else-if="action.intent === 'document' && !action.disabled" as-child :disabled="busy" :data-action="action.key">
            <a :href="action.url" target="_blank" rel="noopener noreferrer"><component :is="menuIcon(action)" v-if="menuIcon(action)" class="size-4" aria-hidden="true" /><ActionLabel :action="action" menu /></a>
        </DropdownMenuItem>
        <DropdownMenuItem v-else :disabled="busy || action.disabled" :class="action.destructive ? 'text-destructive focus:text-destructive' : undefined" :title="action.reason ?? undefined" :data-action="action.key" @select="select(action)">
            <component :is="menuIcon(action)" v-if="menuIcon(action)" class="size-4" aria-hidden="true" /><ActionLabel :action="action" menu />
        </DropdownMenuItem>
    </template>
</template>
