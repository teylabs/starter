import { computed, onBeforeUnmount, onMounted, reactive, type Ref } from 'vue';

/*
 * The sticky-bar registry: every bar pinned to the viewport's foot (FloatingToolbar, a form's Save bar) registers its height here, and the shell's Toaster
 * offsets by the total so a toast never covers it. One module-level store per app; a bar unregisters when it unmounts. `track(el)` measures an element with a
 * ResizeObserver; `register(height)` takes a fixed height in pixels and returns the unregister function.
 */
const bars = reactive(new Map<symbol, number>());

export function stickyBarsHeight(heights: Iterable<number>): number {
    let total = 0;
    for (const height of heights) total += Number.isFinite(height) && height > 0 ? height : 0;
    return total;
}

export function useStickyBars() {
    const height = computed(() => stickyBarsHeight(bars.values()));
    const count = computed(() => bars.size);
    function register(px: number): () => void {
        const id = Symbol('sticky-bar');
        bars.set(id, px);
        return () => bars.delete(id);
    }
    // For a component: measure `el` while mounted (0 while hidden), unregister on unmount.
    function track(el: Ref<HTMLElement | null | undefined>, enabled: () => boolean = () => true): void {
        const id = Symbol('sticky-bar');
        let observer: ResizeObserver | undefined;
        onMounted(() => {
            const measure = () => (enabled() && el.value ? bars.set(id, el.value.getBoundingClientRect().height) : bars.delete(id));
            measure();
            if (el.value && 'ResizeObserver' in window) {
                observer = new ResizeObserver(measure);
                observer.observe(el.value);
            }
        });
        onBeforeUnmount(() => {
            observer?.disconnect();
            bars.delete(id);
        });
    }
    return { height, count, register, track };
}
