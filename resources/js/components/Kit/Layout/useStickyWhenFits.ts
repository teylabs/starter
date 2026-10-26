import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';
import { fitsViewport } from './stickyFit';

// `sticky` follows the element's own height (no fixed height, no inner overflow), so measuring it never feeds back into the result.
export function useStickyWhenFits(enabled: boolean): { el: Ref<HTMLElement | undefined>; sticky: Ref<boolean> } {
    const el = ref<HTMLElement>();
    const sticky = ref(false);
    let observer: ResizeObserver | undefined;
    const measure = () => {
        const node = el.value;
        if (!enabled || !node) { sticky.value = false; return; }
        sticky.value = fitsViewport(node.getBoundingClientRect().height, window.innerHeight, parseFloat(getComputedStyle(document.documentElement).fontSize));
    };
    onMounted(() => {
        if (!enabled) return;
        measure();
        if (typeof ResizeObserver !== 'undefined' && el.value) { observer = new ResizeObserver(measure); observer.observe(el.value); }
        window.addEventListener('resize', measure);
    });
    onBeforeUnmount(() => { observer?.disconnect(); window.removeEventListener('resize', measure); });
    return { el, sticky };
}
