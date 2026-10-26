<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { DropdownMenuItem } from 'reka-ui';
import type { Component } from 'vue';
import { useMenuItem } from '@/components/Kit/Menu/useMenuPanel';

// A plain menu link (DropdownLink.md): a button, an anchor or an Inertia link around the default slot. Ported as specced: the button form is always `type="submit"` (for a form around the menu), and `active` does nothing.
defineOptions({ inheritAttrs: false });
withDefaults(defineProps<{ href?: string; as?: string | Component | null; active?: boolean }>(), { href: '#', as: null });

const item = useMenuItem();
</script>
<template>
    <DropdownMenuItem as="div" class="flex items-start gap-1 outline-hidden transition-colors data-highlighted:bg-gray-100 dark:data-highlighted:bg-gray-700" @click.capture="item.onClickCapture" @select="item.onSelect">
        <button v-if="as === 'button'" class="block w-full px-4 py-2 text-left text-sm/5 text-gray-700 outline-hidden dark:text-gray-200" tabindex="-1" type="submit"><slot /></button>
        <a v-else-if="as === 'a'" class="block px-4 py-2 text-sm/5 text-gray-700 outline-hidden dark:text-gray-200" :href="href" tabindex="-1"><slot /></a>
        <Link v-else class="block px-4 py-2 text-sm/5 text-gray-700 outline-hidden dark:text-gray-200" :href="href" tabindex="-1"><slot /></Link>
    </DropdownMenuItem>
</template>
