<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ModalLink } from '@inertiaui/modal-vue';
import {
    DropdownMenuCheckboxItem,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import ActionLabel from '@/components/Kit/Action/ActionLabel.vue';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import {
    actionIconName,
    menuItemRole,
    opensNewTab,
    rendersAsLink,
    type ActionDescriptor,
} from '@/components/Kit/Action/actionPlacement';
import { deferSelect } from '@/components/Kit/Action/menuSelect';

// The menu rows for a list of actions, inside a DropdownMenuContent: dialog → route modal, document → new tab, a GET without a confirmation → the row itself is the
// link (role="menuitem" on an Inertia Link, or on an <a> in a new tab with `newTab`), everything else handed to `onAction` after the menu closes. `onAction` is a prop, not an emit: the rows unmount with the menu before the deferred call, and an unmounted component's emit is dropped (an
// `@action` listener still works, as Vue passes it as this prop). An action with a boolean `checked` is a checkbox row (the host's DropdownMenuCheckboxItem: role
// "menuitemcheckbox", aria-checked and its check indicator), always handed to `onAction` like an operation; the row shows `checked` and never flips it itself.
const props = defineProps<{
    actions: ActionDescriptor[];
    busy?: boolean;
    onAction?: (action: ActionDescriptor) => void;
}>();
const menuIcon = (action: ActionDescriptor) =>
    actionIcons[actionIconName(action, true) ?? ''];
const select = (action: ActionDescriptor) =>
    deferSelect(props.onAction, action);
</script>
<template>
    <template v-for="action in actions" :key="action.key ?? action.url">
        <DropdownMenuCheckboxItem
            v-if="menuItemRole(action) === 'menuitemcheckbox'"
            :model-value="action.checked === true"
            :disabled="busy || action.disabled"
            :class="
                action.destructive
                    ? 'text-destructive-text focus:text-destructive-text'
                    : undefined
            "
            :title="action.reason ?? undefined"
            :data-action="action.key"
            @select="select(action)"
        >
            <component
                :is="menuIcon(action)"
                v-if="menuIcon(action)"
                class="size-4"
                aria-hidden="true"
            /><ActionLabel :action="action" menu />
        </DropdownMenuCheckboxItem>
        <DropdownMenuItem
            v-else-if="action.intent === 'dialog' && !action.disabled"
            as-child
            :disabled="busy"
            :data-action="action.key"
        >
            <ModalLink
                :href="action.url"
                :method="
                    action.method.toLowerCase() as
                        'get' | 'post' | 'put' | 'patch' | 'delete'
                "
                :navigate="action.method.toLowerCase() === 'get'"
                ><component
                    :is="menuIcon(action)"
                    v-if="menuIcon(action)"
                    class="size-4"
                    aria-hidden="true" /><ActionLabel :action="action" menu
            /></ModalLink>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-else-if="action.intent === 'document' && !action.disabled"
            as-child
            :disabled="busy"
            :data-action="action.key"
        >
            <a :href="action.url" target="_blank" rel="noopener noreferrer"
                ><component
                    :is="menuIcon(action)"
                    v-if="menuIcon(action)"
                    class="size-4"
                    aria-hidden="true" /><ActionLabel :action="action" menu
            /></a>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-else-if="rendersAsLink(action)"
            as-child
            :disabled="busy"
            :data-action="action.key"
        >
            <a
                v-if="opensNewTab(action)"
                :href="action.url"
                target="_blank"
                rel="noopener noreferrer"
                ><component
                    :is="menuIcon(action)"
                    v-if="menuIcon(action)"
                    class="size-4"
                    aria-hidden="true" /><ActionLabel :action="action" menu
            /></a>
            <Link v-else :href="action.url"
                ><component
                    :is="menuIcon(action)"
                    v-if="menuIcon(action)"
                    class="size-4"
                    aria-hidden="true" /><ActionLabel :action="action" menu
            /></Link>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-else
            :disabled="busy || action.disabled"
            :class="
                action.destructive
                    ? 'text-destructive-text focus:text-destructive-text'
                    : undefined
            "
            :title="action.reason ?? undefined"
            :data-action="action.key"
            @select="select(action)"
        >
            <component
                :is="menuIcon(action)"
                v-if="menuIcon(action)"
                class="size-4"
                aria-hidden="true"
            /><ActionLabel :action="action" menu />
        </DropdownMenuItem>
    </template>
</template>
