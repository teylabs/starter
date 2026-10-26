<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import type { PropType } from 'vue';
import MenuPanel from '@/components/Kit/Menu/MenuPanel.vue';
import MenuTrigger from '@/components/Kit/Menu/MenuTrigger.vue';

// A menu opened from a labelled chevron trigger, the menu half of a split button used on its own (ButtonDropdown.md). The trigger has no surface of its own: the caller's classes, or a surrounding group, decide how it looks.
// Renders nothing unless it has a `default` slot or a `label`. Caller attributes land on the trigger button.
defineOptions({ inheritAttrs: false });
defineProps({
    label: { type: String, default: null },
    offset: { type: Array as unknown as PropType<[number, number]>, default: () => [0, 10] },
});
</script>
<template>
    <div class="inline-flex">
        <div v-if="$slots.default || label" class="relative -ml-px block">
            <MenuPanel content-class="z-50 w-64 divide-y rounded-md bg-paper shadow-lg outline-1 outline-black/5 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10" flip-align :side-offset="offset[1]" :skid="offset[0]">
                <template #trigger>
                    <MenuTrigger>
                        <button v-bind="$attrs" class="relative inline-flex items-center focus:z-10" type="button">
                            <span class="px-1 text-sm"><slot>{{ label }}</slot></span>
                            <span class="sr-only">Open options</span>
                            <ChevronDown aria-hidden="true" class="size-5" />
                        </button>
                    </MenuTrigger>
                </template>
                <div v-if="$slots.menuheader" class="px-4 py-3 text-wrap"><slot name="menuheader" /></div>
                <div class="py-1"><slot name="menu" /></div>
            </MenuPanel>
        </div>
    </div>
</template>
