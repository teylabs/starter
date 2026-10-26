<script setup lang="ts">
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import { actionIconName, opensNewTab } from '@/components/Kit/Action/actionPlacement';

// An action's face in every bar and menu: its icon, the label (truncating) and, for one that opens a new tab, a trailing arrow with screen-reader text.
// `menu`: the menu row draws the icon itself, once.
const props = defineProps<{ action: { label: string; icon?: string | null; newTab?: boolean | null; intent?: string | null; key?: string | null }; menu?: boolean }>();
const icon = computed(() => (props.menu ? undefined : actionIcons[actionIconName(props.action) ?? '']));
const newTab = computed(() => opensNewTab(props.action));
</script>
<template>
    <span class="inline-flex min-w-0 items-center gap-1.5 align-top leading-5"><component :is="icon" v-if="icon" class="size-4 shrink-0" aria-hidden="true" /><span class="truncate">{{ action.label }}</span><template v-if="newTab"><ArrowUpRight class="size-3.5 shrink-0 opacity-70" aria-hidden="true" data-new-tab-arrow /><span class="sr-only"> (opens in new tab)</span></template></span>
</template>
