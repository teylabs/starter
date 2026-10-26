<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useMenuPanel } from '@/components/Kit/Menu/useMenuPanel';

// INTERNAL to the menu family. Turns its one child element into the menu's trigger, with no wrapper of its own; the child keeps its attributes and listeners. Reka names the menu (`aria-controls`, `aria-labelledby`) from the trigger's first render.
const menu = useMenuPanel();
const trigger = ref<{ $el: HTMLElement } | null>(null);
onMounted(() => menu?.setTrigger(trigger.value?.$el ?? null));
onBeforeUnmount(() => menu?.setTrigger(null));
</script>
<template>
    <DropdownMenuTrigger ref="trigger" as-child @keydown.up="menu?.openFromEnd($event)"><slot /></DropdownMenuTrigger>
</template>
