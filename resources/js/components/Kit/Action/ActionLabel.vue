<script setup lang="ts">
import { ArrowUpRight, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import {
    actionIconName,
    opensNewTab,
} from '@/components/Kit/Action/actionPlacement';

// An action's face in every bar and menu: its icon, the label (truncating) and, for one that opens a new tab, a trailing arrow with screen-reader text.
// `menu`: the menu row draws the icon itself, once. `pending`: the action's request is in flight; a spinner takes the icon's place (data-action-pending).
const props = defineProps<{
    action: {
        label: string;
        icon?: string | null;
        newTab?: boolean | null;
        intent?: string | null;
        key?: string | null;
    };
    menu?: boolean;
    pending?: boolean;
}>();
const icon = computed(() =>
    props.menu ? undefined : actionIcons[actionIconName(props.action) ?? ''],
);
const newTab = computed(() => opensNewTab(props.action));
</script>
<template>
    <span class="inline-flex min-w-0 items-center gap-1.5 align-top leading-5"
        ><LoaderCircle
            v-if="pending"
            class="size-4 shrink-0 animate-spin motion-reduce:animate-none"
            aria-hidden="true"
            data-action-pending
        /><component
            :is="icon"
            v-else-if="icon"
            class="size-4 shrink-0"
            aria-hidden="true"
        /><span class="truncate">{{ action.label }}</span
        ><template v-if="newTab"
            ><ArrowUpRight
                class="size-3.5 shrink-0 opacity-70"
                aria-hidden="true"
                data-new-tab-arrow
            /><span class="sr-only"> (opens in new tab)</span></template
        ></span
    >
</template>
