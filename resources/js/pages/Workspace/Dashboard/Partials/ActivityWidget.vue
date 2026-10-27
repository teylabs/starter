<script setup lang="ts">
import type { WidgetFrame } from './types';

// The workspace's recent activity, newest first: who did what, and when (in the viewer's own zone and locale).
type Item = { id: string; actor: string; event: string; at: string };
defineProps<{ widget: WidgetFrame & { items: Item[] } }>();
const when = (at: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(at));
</script>
<template>
    <ol class="grid" data-widget-activity>
        <li v-for="item in widget.items" :key="item.id" class="flex flex-wrap items-baseline justify-between gap-x-3 border-t py-2 first:border-0 first:pt-0">
            <p class="text-sm"><span class="font-medium">{{ item.actor }}</span> · {{ item.event }}</p>
            <time :datetime="item.at" class="text-xs text-muted-foreground">{{ when(item.at) }}</time>
        </li>
    </ol>
</template>
