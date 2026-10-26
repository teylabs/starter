<script setup lang="ts">
import { computed } from 'vue';
import DropdownConfirm from '@/components/Kit/Menu/DropdownConfirm.vue';
import DropdownItem from '@/components/Kit/Menu/DropdownItem.vue';

// A menu entry from a server action descriptor (DropdownAction.md): a DropdownConfirm when the action carries a `confirmationMessage`, else an Inertia DropdownItem. `icon` is a CSS class for an `<i>`.
const props = defineProps<{ action: { url: string; method?: string; label: string; icon?: string; description?: string; variant?: string; confirmationMessage?: string } }>();
const iconClass = computed(() => (props.action.variant === 'danger' ? 'text-destructive' : ''));
</script>
<template>
    <DropdownConfirm v-if="action.confirmationMessage" :href="action.url" :method="action.method" :variant="action.variant">
        <template #icon><i aria-hidden="true" :class="[action.icon, iconClass]" /></template>
        {{ action.label }}
        <template v-if="action.description" #caption>{{ action.description }}</template>
        <template #confirmation><p>{{ action.confirmationMessage }}</p></template>
    </DropdownConfirm>
    <DropdownItem v-else as="button" :href="action.url" inertia :method="action.method">
        <template #icon><i aria-hidden="true" :class="[action.icon, iconClass]" /></template>
        {{ action.label }}
        <template v-if="action.description" #caption>{{ action.description }}</template>
    </DropdownItem>
</template>
