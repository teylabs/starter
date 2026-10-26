<script setup lang="ts">
import { Ellipsis, EllipsisVertical } from '@lucide/vue';
import { DropdownMenuItem } from 'reka-ui';
import { Comment, Fragment, Text, cloneVNode, h, useSlots, type VNode } from 'vue';
import MenuPanel from '@/components/Kit/Menu/MenuPanel.vue';
import MenuTrigger from '@/components/Kit/Menu/MenuTrigger.vue';

// An animated icon-trigger menu that turns each top-level element in its default slot into a menu item (DropdownMenu.md). Not the shadcn `DropdownMenu` primitive. As specced, the item rows are not wired to the menu context, so Enter or Space on a highlighted row does not activate the link or button inside it.
defineProps<{ alignLeft?: boolean; horizontal?: boolean }>();
const slots = useSlots();

// The default slot's top-level nodes: fragments (v-for, v-if groups) opened up, comments and blank text dropped.
function topLevelNodes(nodes: VNode[]): VNode[] {
    return nodes.flatMap((node) => {
        if (node.type === Fragment) return topLevelNodes((node.children as VNode[] | null) ?? []);
        if (node.type === Comment) return [];
        if (node.type === Text) return String(node.children).trim() ? [h('span', null, node.children as string)] : [];

        return [node];
    });
}

function MenuItems() {
    return topLevelNodes(slots.default?.() ?? []).map((node) =>
        h(DropdownMenuItem, { as: 'div', class: 'group transition-colors outline-hidden data-highlighted:bg-gray-100 dark:data-highlighted:bg-gray-700' }, () =>
            cloneVNode(node, { class: 'block px-4 py-2 text-sm text-gray-700 group-data-highlighted:text-gray-900 dark:text-gray-200 dark:group-data-highlighted:text-gray-200', tabindex: -1 }),
        ),
    );
}
</script>
<template>
    <div class="relative inline-block text-left">
        <MenuPanel animated :align="alignLeft ? 'start' : 'end'" content-class="z-50 w-56 divide-y divide-gray-200 rounded-md bg-white shadow-lg ring-1 ring-black/5" :side-offset="8">
            <template #trigger>
                <MenuTrigger>
                    <slot name="button">
                        <button class="inline-flex items-center gap-x-1 text-gray-400 hover:text-gray-600" type="button">
                            <span class="sr-only">Open options</span>
                            <slot name="buttontext" />
                            <Ellipsis v-if="horizontal" aria-hidden="true" class="size-5" />
                            <EllipsisVertical v-else aria-hidden="true" class="size-5" />
                        </button>
                    </slot>
                </MenuTrigger>
            </template>
            <div v-if="$slots.header" class="px-4 py-3"><slot name="header" /></div>
            <div class="py-1"><MenuItems /></div>
        </MenuPanel>
    </div>
</template>
