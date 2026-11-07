<script setup lang="ts">
import { ModalLink } from '@inertiaui/modal-vue';
import DropdownItem from '@/components/Kit/Menu/DropdownItem.vue';

// A menu entry that opens a route modal (DropdownModalLink.md): the menu closes first, then the modal opens from the link.
defineProps<{ href: string }>();
</script>
<template>
    <DropdownItem>
        <template #wrapper="{ close }">
            <ModalLink
                :before-open="close"
                class="text-popover-foreground block w-full px-4 py-2 text-left text-sm/5 outline-hidden"
                :href="href"
                tabindex="-1"
            >
                <span class="flex items-start gap-2">
                    <span class="shrink-0"><slot name="icon" /></span>
                    <span class="min-w-0">
                        <span class="block"><slot /></span>
                        <span
                            v-if="$slots.caption"
                            class="text-muted-foreground block text-xs text-pretty"
                            ><slot name="caption"
                        /></span>
                    </span>
                </span>
            </ModalLink>
        </template>
    </DropdownItem>
</template>
