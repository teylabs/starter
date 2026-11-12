<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, useId, watchEffect, type Directive } from 'vue';
import { ModalLink } from '@inertiaui/modal-vue';
import {
    DropdownMenuCheckboxItem,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import ActionLabel from '@/components/Kit/Action/ActionLabel.vue';
import {
    actionIcons,
    mixedIconsWarning,
} from '@/components/Kit/Action/actionIcons';
import {
    actionIconName,
    menuItemRole,
    opensNewTab,
    rendersAsLink,
    type ActionDescriptor,
} from '@/components/Kit/Action/actionPlacement';
import { deferSelect } from '@/components/Kit/Action/menuSelect';
import { useMenuItemClass } from '@/components/Kit/Action/menuItemClass';

// The menu rows for a list of actions, inside a DropdownMenuContent: dialog → route modal, document → new tab, a GET without a confirmation → the row itself is the
// link (role="menuitem" on an Inertia Link, or on an <a> in a new tab with `newTab`), everything else handed to `onAction` after the menu closes. `onAction` is a prop, not an emit: the rows unmount with the menu before the deferred call, and an unmounted component's emit is dropped (an
// `@action` listener still works, as Vue passes it as this prop). An action with a boolean `checked` is a checkbox row (the host's DropdownMenuCheckboxItem: role
// "menuitemcheckbox", aria-checked and its check indicator), always handed to `onAction` like an operation; the row shows `checked` and never flips it itself.
const props = defineProps<{
    actions: ActionDescriptor[];
    busy?: boolean;
    onAction?: (action: ActionDescriptor) => void;
    itemClass?: string;
}>();
// The host's own row classes (ActionBar's itemClass, or a ButtonGroup's around this menu), added after the row's own; destructive and disabled still apply.
const groupItemClass = useMenuItemClass();
const rowClass = computed(() => props.itemClass ?? groupItemClass());
const menuIcon = (action: ActionDescriptor) =>
    actionIcons[actionIconName(action, true) ?? ''];
// A row without an icon, in a menu where another row has one, keeps an icon-sized spacer so its label aligns with theirs. Dev builds warn once when a menu mixes.
const anyIcon = computed(() =>
    props.actions.some((action) => menuIcon(action)),
);
const warnMixed = mixedIconsWarning('ActionBar menu');
watchEffect(() =>
    warnMixed(props.actions.map((action) => Boolean(menuIcon(action)))),
);
// A `disabled` row stays focusable (aria-disabled, not the menu's own disabled, which skips it) so its `reason` shows as a tooltip and is read as its description;
// choosing it does nothing and the menu stays open.
const select = (action: ActionDescriptor) =>
    deferSelect(props.onAction, action);
function choose(action: ActionDescriptor, event: Event) {
    if (action.disabled) return event.preventDefault();
    select(action);
}
// Reka's menu item writes its own aria-disabled (from its `disabled`, which would also drop the row from keyboard navigation) over a passed one, so a row that
// stays focusable but cannot be chosen sets the attribute itself once rendered.
const vAriaDisabled: Directive<HTMLElement, boolean | undefined> = {
    mounted: (el, { value }) => ariaDisabled(el, value),
    updated: (el, { value }) => ariaDisabled(el, value),
};
function ariaDisabled(el: HTMLElement, value: boolean | undefined) {
    if (value) el.setAttribute('aria-disabled', 'true');
    else el.removeAttribute('aria-disabled');
}
const baseId = useId();
const reasonId = (index: number) => `${baseId}-reason-${index}`;
const describes = (action: ActionDescriptor) =>
    Boolean(action.disabled && action.reason);
</script>
<template>
    <template
        v-for="(action, index) in actions"
        :key="action.key ?? action.url"
    >
        <DropdownMenuCheckboxItem
            v-if="menuItemRole(action) === 'menuitemcheckbox'"
            :model-value="action.checked === true"
            :disabled="busy"
            v-aria-disabled="action.disabled"
            :class="[
                action.destructive &&
                    'text-destructive-text focus:text-destructive-text',
                action.disabled && 'cursor-not-allowed opacity-50',
                rowClass,
            ]"
            :title="(describes(action) && action.reason) || undefined"
            :aria-describedby="describes(action) ? reasonId(index) : undefined"
            :aria-label="action.ariaLabel || undefined"
            :data-action="action.key"
            @select="choose(action, $event)"
        >
            <component
                :is="menuIcon(action)"
                v-if="menuIcon(action)"
                class="size-4"
                aria-hidden="true"
            /><span
                v-else-if="anyIcon"
                class="inline-block size-4 shrink-0"
                aria-hidden="true"
                data-menu-icon-spacer
            /><ActionLabel :action="action" menu /><span
                v-if="describes(action)"
                :id="reasonId(index)"
                class="sr-only"
                aria-hidden="true"
                data-action-reason
                >{{ action.reason }}</span
            >
        </DropdownMenuCheckboxItem>
        <DropdownMenuItem
            v-else-if="action.intent === 'dialog' && !action.disabled"
            as-child
            :disabled="busy"
            :class="rowClass"
            :aria-label="action.ariaLabel || undefined"
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
                    aria-hidden="true" /><span
                    v-else-if="anyIcon"
                    class="inline-block size-4 shrink-0"
                    aria-hidden="true"
                    data-menu-icon-spacer /><ActionLabel :action="action" menu
            /></ModalLink>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-else-if="action.intent === 'document' && !action.disabled"
            as-child
            :disabled="busy"
            :class="rowClass"
            :aria-label="action.ariaLabel || undefined"
            :data-action="action.key"
        >
            <a :href="action.url" target="_blank" rel="noopener noreferrer"
                ><component
                    :is="menuIcon(action)"
                    v-if="menuIcon(action)"
                    class="size-4"
                    aria-hidden="true" /><span
                    v-else-if="anyIcon"
                    class="inline-block size-4 shrink-0"
                    aria-hidden="true"
                    data-menu-icon-spacer /><ActionLabel :action="action" menu
            /></a>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-else-if="rendersAsLink(action)"
            as-child
            :disabled="busy"
            :class="rowClass"
            :aria-label="action.ariaLabel || undefined"
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
                    aria-hidden="true" /><span
                    v-else-if="anyIcon"
                    class="inline-block size-4 shrink-0"
                    aria-hidden="true"
                    data-menu-icon-spacer /><ActionLabel :action="action" menu
            /></a>
            <Link v-else :href="action.url"
                ><component
                    :is="menuIcon(action)"
                    v-if="menuIcon(action)"
                    class="size-4"
                    aria-hidden="true" /><span
                    v-else-if="anyIcon"
                    class="inline-block size-4 shrink-0"
                    aria-hidden="true"
                    data-menu-icon-spacer /><ActionLabel :action="action" menu
            /></Link>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-else
            :disabled="busy"
            v-aria-disabled="action.disabled"
            :class="[
                action.destructive &&
                    'text-destructive-text focus:text-destructive-text',
                action.disabled && 'cursor-not-allowed opacity-50',
                rowClass,
            ]"
            :title="(describes(action) && action.reason) || undefined"
            :aria-describedby="describes(action) ? reasonId(index) : undefined"
            :aria-label="action.ariaLabel || undefined"
            :data-action="action.key"
            @select="choose(action, $event)"
        >
            <component
                :is="menuIcon(action)"
                v-if="menuIcon(action)"
                class="size-4"
                aria-hidden="true"
            /><span
                v-else-if="anyIcon"
                class="inline-block size-4 shrink-0"
                aria-hidden="true"
                data-menu-icon-spacer
            /><ActionLabel :action="action" menu /><span
                v-if="describes(action)"
                :id="reasonId(index)"
                class="sr-only"
                aria-hidden="true"
                data-action-reason
                >{{ action.reason }}</span
            >
        </DropdownMenuItem>
    </template>
</template>
