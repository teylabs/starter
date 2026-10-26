<script setup lang="ts">
import { ChevronDown, Ellipsis, EllipsisVertical, LoaderCircle } from '@lucide/vue';
import MenuPanel from '@/components/Kit/Menu/MenuPanel.vue';
import MenuTrigger from '@/components/Kit/Menu/MenuTrigger.vue';

// The general-purpose menu (Dropdown.md): a labelled button trigger (`type="button"`) or a quiet icon trigger (ellipsis, chevron or spinner), over the shared MenuPanel. Caller attributes land on the trigger button.
defineOptions({ inheritAttrs: false });
withDefaults(defineProps<{ alignLeft?: boolean; horizontal?: boolean; type?: string; label?: string; colorClasses?: string; chevron?: boolean; spin?: boolean; menuClasses?: string }>(), { type: '', label: '', colorClasses: '', menuClasses: '' });
</script>
<template>
    <div class="relative inline-block text-left">
        <MenuPanel :align="alignLeft ? 'start' : 'end'" :content-class="['z-50 w-64 divide-y divide-gray-200 rounded-md bg-white shadow-lg ring-1 ring-black/5', menuClasses]" flip-align :side-offset="10">
            <template #trigger>
                <MenuTrigger>
                    <button v-if="type === 'button'" v-bind="$attrs" :class="['inline-flex h-9 w-full items-center justify-center gap-x-1.5 rounded-md px-3 py-2 text-sm font-medium shadow-xs', colorClasses || 'bg-white text-gray-900 ring-1 ring-gray-300 ring-inset hover:bg-gray-50']" type="button">
                        <slot name="buttontext">{{ label }}</slot>
                        <ChevronDown aria-hidden="true" class="-mr-1 size-5 text-gray-400" />
                    </button>
                    <button v-else v-bind="$attrs" class="inline-flex items-center gap-x-1 rounded-md text-gray-400 hover:text-gray-600" type="button">
                        <span class="sr-only">Open options</span>
                        <slot name="buttontext">{{ label }}</slot>
                        <LoaderCircle v-if="spin" aria-hidden="true" class="size-4 animate-spin" />
                        <slot v-else name="trigger">
                            <ChevronDown v-if="chevron" aria-hidden="true" class="-mr-1 size-5" />
                            <Ellipsis v-else-if="horizontal" aria-hidden="true" class="-mr-1 size-5" />
                            <EllipsisVertical v-else aria-hidden="true" class="-mr-1 size-5" />
                        </slot>
                    </button>
                </MenuTrigger>
            </template>
            <div v-if="$slots.header" class="px-4 py-3 break-all"><slot name="header" /></div>
            <div class="py-1"><slot /></div>
        </MenuPanel>
    </div>
</template>
