<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { DropdownMenuItem } from 'reka-ui';
import type { Component } from 'vue';
import { useMenuItem, useMenuPanel } from '@/components/Kit/Menu/useMenuPanel';

// One entry in a kit menu (DropdownItem.md): a button, a plain link or an Inertia link, with an optional icon and caption, or the caller's own control in the `wrapper` slot. Must sit inside a kit menu. Caller attributes land on the inner control.
// `active` is declared and does nothing (an owner question in the spec); a link left at the `#` placeholder is not followed.
defineOptions({ inheritAttrs: false });
withDefaults(defineProps<{ href?: string; as?: string | Component | null; inertia?: boolean; active?: boolean }>(), { href: '#', as: null });

const menu = useMenuPanel();
const item = useMenuItem();
const controlClass = 'block w-full px-4 py-2 text-left text-sm/5 text-gray-700 outline-hidden dark:text-gray-200';
const contentClass = 'flex items-start gap-2';
const captionClass = 'block text-xs text-pretty text-gray-500 dark:text-gray-300';
const close = () => menu?.close();
</script>
<template>
    <DropdownMenuItem as="div" class="flex items-start outline-hidden transition-colors data-highlighted:bg-gray-100 dark:data-highlighted:bg-gray-700" @click.capture="item.onClickCapture" @select="item.onSelect">
        <slot name="wrapper" :close="close">
            <button v-if="as === 'button' && !inertia" v-bind="$attrs" :class="controlClass" tabindex="-1" type="button">
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span v-if="$slots.caption" :class="captionClass"><slot name="caption" /></span>
                    </span>
                </span>
            </button>
            <a v-else-if="as === 'a'" v-bind="$attrs" :class="controlClass" :href="href" tabindex="-1">
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span v-if="$slots.caption" :class="captionClass"><slot name="caption" /></span>
                    </span>
                </span>
            </a>
            <Link v-else v-bind="$attrs" :as="as ?? 'a'" :class="controlClass" :href="href" tabindex="-1">
                <span :class="contentClass">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span v-if="$slots.caption" :class="[captionClass, 'mt-1']"><slot name="caption" /></span>
                    </span>
                </span>
            </Link>
        </slot>
    </DropdownMenuItem>
</template>
