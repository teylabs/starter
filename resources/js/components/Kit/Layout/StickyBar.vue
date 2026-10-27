<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useStickyBars } from './stickyBars';

/*
 * A full-width bar pinned to its container's foot while the container's end is below the fold, and back in flow at the end: the edge-to-edge sibling of
 * FloatingToolbar (a centred pill of ActionBar descriptors). Use StickyBar for a page's own controls that read as part of the page (a pager, a summary
 * and its totals, filters you keep at hand); use FloatingToolbar for page actions. While pinned it registers its height with useStickyBars, so the
 * Toaster, BulkBar and other bars stack clear of it. `surface` keeps the card look when not pinned. The slot gets `{ pinned }`. Ancestors must not clip
 * overflow. Honours the bottom safe-area inset.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{ sticky?: boolean; surface?: boolean }>(), { sticky: true, surface: false });
const bar = ref<HTMLElement>();
const sentinel = ref<HTMLElement>();
const pinned = ref(false);
useStickyBars().track(bar, () => props.sticky && pinned.value);
let observer: IntersectionObserver | undefined;
onMounted(() => {
    if (!sentinel.value || !('IntersectionObserver' in window)) return;
    // The sentinel sits just after the bar: while it is below the viewport, the bar is pinned.
    observer = new IntersectionObserver(([entry]) => { pinned.value = !entry.isIntersecting && entry.boundingClientRect.top > 0; }, { threshold: 0 });
    observer.observe(sentinel.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>
<template>
    <div
        ref="bar"
        v-bind="$attrs"
        data-sticky-bar
        :data-pinned="sticky && pinned ? 'true' : 'false'"
        :class="[
            'z-30 flex flex-wrap items-center gap-3 border-t px-4 py-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] transition-colors',
            sticky && 'sticky bottom-0',
            surface ? 'border-border bg-card shadow-[0_-4px_12px_-6px_rgb(0_0_0/0.18)]' : sticky && pinned ? 'border-border bg-background/95 shadow-[0_-4px_12px_-8px_rgb(0_0_0/0.15)] backdrop-blur' : 'border-transparent',
        ]"
    >
        <slot :pinned="pinned" />
    </div>
    <div ref="sentinel" aria-hidden="true" class="h-px" data-sticky-sentinel />
</template>
