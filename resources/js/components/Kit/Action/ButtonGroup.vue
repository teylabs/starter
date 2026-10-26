<template>
    <div class="inline-flex rounded-md shadow-xs dark:shadow-none">
        <div
            v-if="$slots.default"
            :class="[
                'relative inline-flex items-center divide-x divide-outline rounded-l-md bg-paper ring-1! ring-outline! ring-inset',
                $slots.menu
                    ? 'rounded-r-none'
                    : 'rounded-r-md *:last:rounded-r-md',
                '*:px-3 *:py-2 *:first:rounded-l-md [&>*:focus]:z-50 [&>*:focus]:outline-hidden [&>*:hover]:z-50 [&>*:hover]:opacity-70 [&>*:hover]:outline-transparent',
                '*:truncate *:text-sm *:font-medium *:text-outline-foreground',
            ]"
        >
            <slot />
        </div>
        <div v-if="$slots.menu" class="relative -ml-px block">
            <MenuPanel
                content-class="z-50 w-64 origin-top-right divide-y rounded-md bg-popover text-popover-foreground p-0 shadow-lg outline-1! outline-black/5! outline-solid! dark:shadow-none dark:-outline-offset-1 dark:outline-white/10!"
                :side-offset="offset[1]"
                :skid="offset[0]"
            >
                <template #trigger>
                    <MenuTrigger>
                        <button
                            :class="[
                                !$slots.default ? 'rounded-l-md' : '',
                                'relative inline-flex items-center rounded-r-md bg-paper px-2 py-2 ring-1 ring-outline ring-inset hover:bg-outline-wash focus:z-10',
                            ]"
                            type="button"
                        >
                            <div
                                v-if="$slots.label || label"
                                class="px-1 text-sm"
                            >
                                <slot name="label">
                                    {{ label }}
                                </slot>
                            </div>
                            <span class="sr-only">Open options</span>
                            <ChevronDown
                                aria-hidden="true"
                                class="size-5 text-outline-muted"
                            />
                        </button>
                    </MenuTrigger>
                </template>
                <div v-if="$slots.menuheader" class="px-4 py-3 text-wrap">
                    <slot name="menuheader" />
                </div>
                <div class="py-1">
                    <slot name="menu" />
                </div>
            </MenuPanel>
        </div>
    </div>
</template>

<script setup lang="ts">
// Jasper's original ButtonGroup, as is. Its menu runs on the kit's menu family (MenuPanel), the
// chevron is Lucide, and the script typed for the kit's build. Its look comes from the kit's `paper` and outline tokens (kit.css), whose defaults are the old
// gray/white values; a theme, or a surface such as the FloatingToolbar pill, overrides the tokens to restyle it.
import { ChevronDown } from '@lucide/vue';
import type { PropType } from 'vue';

import MenuPanel from '@/components/Kit/Menu/MenuPanel.vue';
import MenuTrigger from '@/components/Kit/Menu/MenuTrigger.vue';

defineProps({
    label: {
        type: String,
        default: null,
    },
    offset: {
        type: Array as unknown as PropType<[number, number]>,
        default: () => [0, 10],
    },
});
</script>
