<script setup lang="ts">
import { ScrollAreaRoot, ScrollAreaScrollbar, ScrollAreaThumb, ScrollAreaViewport } from 'reka-ui';

// A pane's own vertical scroll, filling a bounded-height parent. Overscroll is contained; `chain` passes the wheel on to the page at either end (and when nothing overflows), for a pane in the page's own scroll (a sticky aside). Not an infinite-scroll loader.
defineProps<{ chain?: boolean }>();
</script>
<template>
    <ScrollAreaRoot type="auto" class="relative h-full min-h-0 min-w-0">
        <ScrollAreaViewport class="h-full w-full pr-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring *:h-full" :class="chain ? 'overscroll-auto' : 'overscroll-contain'" :data-chain="chain || undefined"><slot /></ScrollAreaViewport>
        <ScrollAreaScrollbar orientation="vertical" class="flex w-2 touch-none rounded-full bg-muted p-0.5 select-none" data-pane-scrollbar><ScrollAreaThumb class="relative flex-1 rounded-full bg-muted-foreground/60" /></ScrollAreaScrollbar>
    </ScrollAreaRoot>
</template>
