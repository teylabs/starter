<script setup lang="ts">
import { PanelRightClose, PanelRightOpen } from '@lucide/vue';
import { Button as BaseButton } from '@/components/ui/button';

// The collapse/expand icon button for a pane (28px; no separate phone tap-target claim). It owns no state: the host passes `collapsed` and listens for click.
withDefaults(defineProps<{ label: string; collapsed?: boolean }>(), { collapsed: false });
</script>
<template>
    <BaseButton type="button" variant="ghost" class="size-7 shrink-0 p-0" :aria-label="`${collapsed ? 'Expand' : 'Collapse'} ${label.toLowerCase()} pane`" :title="`${collapsed ? 'Expand' : 'Collapse'} ${label.toLowerCase()} pane`" :aria-expanded="!collapsed" data-pane-toggle>
        <PanelRightOpen v-if="collapsed" class="size-4" aria-hidden="true" /><PanelRightClose v-else class="size-4" aria-hidden="true" />
    </BaseButton>
</template>
