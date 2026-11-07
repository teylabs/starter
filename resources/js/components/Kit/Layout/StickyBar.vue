<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useStickyBars } from './stickyBars';

/*
 * A full-width bar pinned to its container's foot while the container's end is below the fold, and back in flow at the end: the edge-to-edge sibling of
 * FloatingToolbar (a centred pill of ActionBar descriptors). Use StickyBar for a page's own controls that read as part of the page (a pager, a summary
 * and its totals, filters you keep at hand); use FloatingToolbar for page actions. While pinned it registers its height with useStickyBars, so the
 * Toaster, BulkBar and other bars stack clear of it. `surface` keeps the card look when not pinned. The slot gets `{ pinned }`. Ancestors must not clip
 * overflow. Honours the bottom safe-area inset. `pinned` says the layout already holds it at the viewport's foot (a filling Workbench, a bottom-docked panel):
 * it then counts as pinned whatever the scroll, so it registers and toasts stack above it. While pinned, a control that takes focus under the bar is
 * scrolled into view above it; focus never moves.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{ sticky?: boolean; surface?: boolean; pinned?: boolean }>(),
    { sticky: true, surface: false, pinned: false },
);
const bar = ref<HTMLElement>();
const sentinel = ref<HTMLElement>();
const scrolled = ref(false);
// At the viewport's foot by the host's own layout (a `:fill="false"` Workbench in its own full-height column): pinned too, re-measured on scroll and resize.
// Within 12px, since what sits after the bar in its container (a grid gap, this bar's own 1px sentinel) keeps it a few pixels above the edge.
const atFoot = ref(false);
const pinned = computed(
    () => props.pinned || atFoot.value || (props.sticky && scrolled.value),
);
useStickyBars().track(bar, () => pinned.value);
let observer: IntersectionObserver | undefined;
let resizes: ResizeObserver | undefined;
let frame = 0;
const footCheck = () => {
    const rect = bar.value?.getBoundingClientRect();
    atFoot.value =
        rect !== undefined &&
        rect.height > 0 &&
        Math.abs(window.innerHeight - rect.bottom) <= 12;
};
const laterFoot = () => {
    if (!frame)
        frame = requestAnimationFrame(() => {
            frame = 0;
            footCheck();
        });
};
// A pinned bar must not hide the focused control (WCAG 2.4.11): when focus lands under it, scroll just enough to show the control above it.
// Focus never moves, and nothing happens when the bar is in flow or the control sits inside the bar.
const reveal = (event: FocusEvent) => {
    const target = event.target;
    const shown = bar.value;
    if (
        !pinned.value ||
        !shown ||
        !(target instanceof Element) ||
        shown.contains(target)
    )
        return;
    const field = target.getBoundingClientRect();
    const top = shown.getBoundingClientRect().top;
    if (
        field.bottom > top &&
        field.top < shown.getBoundingClientRect().bottom
    ) {
        window.scrollBy({ top: field.bottom - top + 8, behavior: 'instant' });
    }
};
onMounted(() => {
    document.addEventListener('focusin', reveal);
    footCheck();
    window.addEventListener('resize', laterFoot, { passive: true });
    window.addEventListener('scroll', laterFoot, {
        passive: true,
        capture: true,
    });
    // The layout can move the bar without a scroll or a window resize (content loading above it): re-check when it or the page resizes.
    if ('ResizeObserver' in window) {
        resizes = new ResizeObserver(laterFoot);
        if (bar.value) resizes.observe(bar.value);
        resizes.observe(document.documentElement);
    }
    if (!sentinel.value || !('IntersectionObserver' in window)) return;
    // The sentinel sits just after the bar: while it is below the viewport, the bar is pinned.
    observer = new IntersectionObserver(
        ([entry]) => {
            scrolled.value =
                !entry.isIntersecting && entry.boundingClientRect.top > 0;
        },
        { threshold: 0 },
    );
    observer.observe(sentinel.value);
});
onBeforeUnmount(() => {
    document.removeEventListener('focusin', reveal);
    observer?.disconnect();
    resizes?.disconnect();
    window.removeEventListener('resize', laterFoot);
    window.removeEventListener('scroll', laterFoot, { capture: true });
    if (frame) cancelAnimationFrame(frame);
});
</script>
<template>
    <div
        ref="bar"
        v-bind="$attrs"
        data-sticky-bar
        :data-pinned="pinned ? 'true' : 'false'"
        :class="[
            'z-30 flex flex-wrap items-center gap-3 border-t px-4 py-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] transition-colors',
            sticky && 'sticky bottom-0',
            surface
                ? 'border-border bg-card shadow-[0_-4px_12px_-6px_rgb(0_0_0/0.18)]'
                : pinned
                  ? 'border-border bg-background/95 shadow-[0_-4px_12px_-8px_rgb(0_0_0/0.15)] backdrop-blur'
                  : 'border-transparent',
        ]"
    >
        <slot :pinned="pinned" />
    </div>
    <div ref="sentinel" aria-hidden="true" class="h-px" data-sticky-sentinel />
</template>
