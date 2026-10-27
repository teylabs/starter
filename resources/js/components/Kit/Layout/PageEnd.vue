<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';

/*
 * The end of a long page: Back to top, an optional jump list of the page's sections, and `pagination` / `actions` slots. Place it last in the page's
 * content. It shows only when the content before it runs past one and a half screens (a short page has nothing to return to) and hides while a sticky
 * form bar is pinned in the same <main>, which already holds the page's actions. Jumping moves focus to the target (the page's <h1> for Back to top),
 * and scrolls without animation for reduced motion.
 */
withDefaults(defineProps<{ sections?: { id: string; label: string }[]; label?: string; topLabel?: string; sectionsLabel?: string }>(), { sections: () => [], label: 'End of page', topLabel: 'Back to top', sectionsLabel: 'Jump to a section' });
const marker = ref<HTMLElement>();
const shown = ref(false);
let resize: ResizeObserver | undefined;
let mutations: MutationObserver | undefined;
let frame = 0;

function measure(): void {
    const main = marker.value?.closest('main');
    if (!main || !marker.value) return;
    const pinnedBar = [...main.querySelectorAll<HTMLElement>('[data-form-actions], [data-sticky-bar]')].some((bar) => bar.dataset.pinned !== 'false' && bar.getClientRects().length > 0 && ['sticky', 'fixed'].includes(getComputedStyle(bar).position));
    // Only the content above the marker counts, so showing this cannot itself make the page long enough to show it.
    shown.value = !pinnedBar && marker.value.getBoundingClientRect().top - main.getBoundingClientRect().top > window.innerHeight * 1.5;
}
function later(): void {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(measure);
}
function jump(id?: string): void {
    const main = marker.value?.closest('main');
    const target = id ? document.getElementById(id) : (main?.querySelector<HTMLElement>('h1') ?? main);
    if (!(target instanceof HTMLElement)) return;
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
    const behavior: ScrollBehavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth';
    if (id) target.scrollIntoView({ behavior, block: 'start' });
    else window.scrollTo({ top: 0, behavior });
}
onMounted(() => {
    const main = marker.value?.closest('main');
    if (!main) return;
    if ('ResizeObserver' in window) {
        resize = new ResizeObserver(later);
        resize.observe(main);
    }
    mutations = new MutationObserver(later);
    mutations.observe(main, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'data-pinned'] });
    window.addEventListener('resize', later);
    later();
});
onBeforeUnmount(() => {
    cancelAnimationFrame(frame);
    resize?.disconnect();
    mutations?.disconnect();
    window.removeEventListener('resize', later);
});
</script>
<template>
    <div ref="marker" data-page-end-marker>
        <aside v-if="shown" :aria-label="label" class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-border pt-4 text-sm" data-page-end>
            <div class="flex flex-wrap items-center gap-3">
                <Button type="button" variant="secondary" data-back-to-top @click="jump()">{{ topLabel }}</Button>
                <nav v-if="sections.length" :aria-label="sectionsLabel" class="flex flex-wrap gap-x-3 gap-y-2">
                    <a v-for="section in sections" :key="section.id" :href="`#${section.id}`" class="rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" :data-page-end-section="section.id" @click.prevent="jump(section.id)">{{ section.label }}</a>
                </nav>
            </div>
            <slot name="pagination" />
            <slot name="actions" />
        </aside>
    </div>
</template>
