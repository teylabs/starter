<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { DropdownMenuItem } from 'reka-ui';
import { useMenuItemClass } from '@/components/Kit/Action/menuItemClass';
import { cn } from '@/components/Kit/utils';
import type { Component } from 'vue';
import { useMenuItem, useMenuPanel } from '@/components/Kit/Menu/useMenuPanel';

// One entry in a kit menu (DropdownItem.md): a button, a plain link or an Inertia link, with an optional icon and caption, or the caller's own control in the `wrapper` slot. Must sit inside a kit menu. Caller attributes land on the inner control.
// `active` is declared and does nothing (an owner question in the spec); a link left at the `#` placeholder is not followed.
defineOptions({ inheritAttrs: false });
// `@chosen` fires after the menu has closed: use it, not `@click`, for work that moves or re-renders the item's own row.
const emit = defineEmits<{ chosen: [] }>();
withDefaults(
    defineProps<{
        href?: string;
        as?: string | Component | null;
        inertia?: boolean;
        active?: boolean;
    }>(),
    { href: '#', as: null },
);

const menu = useMenuPanel();
const item = useMenuItem();
const controlClass =
    'block w-full px-4 py-2 text-left text-sm/5 text-popover-foreground outline-hidden';
const contentClass = 'flex items-start gap-2';
const captionClass = 'block text-xs text-pretty text-muted-foreground';
const close = () => menu?.close();
const chosen = () =>
    menu ? menu.afterClose(() => emit('chosen')) : emit('chosen');
// A ButtonGroup's `itemClass`, merged last onto the inner control (so its padding replaces the control's own, not adds to it); with the `wrapper` slot there is
// no inner control, so it goes on the row.
const itemClass = useMenuItemClass();
const rowControlClass = () => cn(controlClass, itemClass());
</script>
<template>
    <DropdownMenuItem
        as="div"
        class="data-highlighted:bg-accent data-highlighted:text-accent-foreground flex items-start outline-hidden transition-colors"
        :class="$slots.wrapper ? itemClass() : undefined"
        @click.capture="item.onClickCapture"
        @select="item.onSelect"
    >
        <slot name="wrapper" :close="close">
            <button
                v-if="as === 'button' && !inertia"
                v-bind="$attrs"
                @click="chosen"
                :class="rowControlClass()"
                tabindex="-1"
                type="button"
            >
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span v-if="$slots.caption" :class="captionClass"
                            ><slot name="caption"
                        /></span>
                    </span>
                </span>
            </button>
            <a
                v-else-if="as === 'a'"
                v-bind="$attrs"
                :class="rowControlClass()"
                :href="href"
                tabindex="-1"
            >
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span v-if="$slots.caption" :class="captionClass"
                            ><slot name="caption"
                        /></span>
                    </span>
                </span>
            </a>
            <Link
                v-else
                v-bind="$attrs"
                :as="as ?? 'a'"
                :class="rowControlClass()"
                :href="href"
                tabindex="-1"
            >
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span
                            v-if="$slots.caption"
                            :class="[captionClass, 'mt-1']"
                            ><slot name="caption"
                        /></span>
                    </span>
                </span>
            </Link>
        </slot>
    </DropdownMenuItem>
</template>
