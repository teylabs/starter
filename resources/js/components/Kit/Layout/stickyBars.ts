import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    watch,
    type Ref,
} from 'vue';

/*
 * The sticky-bar registry: every bar pinned at the viewport's foot (FloatingToolbar, a form's Save bar, a filling Workbench's actions bar) registers its inset
 * here, the distance from the viewport's bottom edge to the bar's top edge, and the shell's Toaster lifts bottom toasts by the largest so a toast never covers one.
 * A bar flush with the bottom has its height as its inset; one held above it by the layout (a Workbench under a shorter shell) has more. One module-level store per
 * app; a bar unregisters when it unmounts. `track(el)` measures an element (on resize, scroll and window resize); `register(px)` takes a fixed inset in pixels and
 * returns the unregister function.
 */
const bars = reactive(new Map<symbol, number>());

/** The clearance bottom toasts need: the largest registered inset (insets all start at the viewport's bottom, so stacked or side-by-side bars overlap). */
export function stickyBarsHeight(insets: Iterable<number>): number {
    let clearance = 0;
    for (const inset of insets)
        clearance =
            Number.isFinite(inset) && inset > clearance ? inset : clearance;
    return clearance;
}

export function useStickyBars() {
    const height = computed(() => stickyBarsHeight(bars.values()));
    const count = computed(() => bars.size);
    function register(px: number): () => void {
        const id = Symbol('sticky-bar');
        bars.set(id, px);
        return () => bars.delete(id);
    }
    // For a component: measure `el` while mounted and `enabled()` (re-checked when it changes, not only on resize), unregister on unmount.
    function track(
        el: Ref<HTMLElement | null | undefined>,
        enabled: () => boolean = () => true,
    ): void {
        const id = Symbol('sticky-bar');
        let observer: ResizeObserver | undefined;
        let mounted = false;
        // The inset: from the viewport's bottom to the bar's top, so a bar the layout holds above the bottom edge is cleared too (never less than its height).
        const inset = (node: HTMLElement) => {
            const rect = node.getBoundingClientRect();
            return Math.max(rect.height, window.innerHeight - rect.top);
        };
        const measure = () =>
            mounted && enabled() && el.value
                ? bars.set(id, inset(el.value))
                : bars.delete(id);
        let frame = 0;
        const later = () => {
            if (!frame)
                frame = requestAnimationFrame(() => {
                    frame = 0;
                    measure();
                });
        };
        watch(enabled, measure, { flush: 'post' });
        onMounted(() => {
            mounted = true;
            measure();
            if (el.value && 'ResizeObserver' in window) {
                observer = new ResizeObserver(measure);
                observer.observe(el.value);
            }
            window.addEventListener('resize', later, { passive: true });
            window.addEventListener('scroll', later, {
                passive: true,
                capture: true,
            });
        });
        onBeforeUnmount(() => {
            mounted = false;
            observer?.disconnect();
            window.removeEventListener('resize', later);
            window.removeEventListener('scroll', later, { capture: true });
            if (frame) cancelAnimationFrame(frame);
            bars.delete(id);
        });
    }
    return { height, count, register, track };
}
