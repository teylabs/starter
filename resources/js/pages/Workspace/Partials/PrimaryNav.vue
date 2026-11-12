<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@kit/ui';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/*
 * The current workspace's pages, with one active mark: aria-current plus an underline on the bar's edge. The nav measures itself: the pages that don't
 * fit the room the bar leaves it go into a "More" menu at the end, in their order. The current page always stays in the bar (the last pages before it
 * step aside instead), so the active mark is never hidden. A hidden copy of every link is measured, so the split never depends on what is shown.
 * Search shrinks first: the room asks for every page's width (its flex-basis), and the bar's search (the Layout's `data-shell-bar-gives` boxes: the `bar` slot
 * and global search) gives up width for it, down to its minimum, before any page goes into More. That minimum is the control's own width or
 * `--shell-bar-control-min` (10rem by default, room for a search field's placeholder), whichever is smaller, so a compact control never grows.
 */
const props = defineProps<{ navigation: Tey.Kit.Features.Shell.Data.ShellData['navigation']; label: string }>();
type Item = Tey.Kit.Features.Shell.Data.ShellData['navigation'][number];

const root = ref<HTMLElement>();
const measure = ref<HTMLElement>();
// How many of the pages (in order, the current one kept) the bar shows; all of them until measured.
const shown = ref<number>(Number.POSITIVE_INFINITY);

const split = computed((): { visible: Item[]; more: Item[] } => {
    const items = props.navigation;
    if (shown.value >= items.length) return { visible: items, more: [] };
    const count = Math.max(shown.value, 0);
    const visible = items.slice(0, count);
    const current = items.findIndex((item) => item.current);
    if (current >= count) {
        // Keep the current page in view: it takes the last place, the page there steps into More.
        visible.splice(Math.max(count - 1, 0), count > 0 ? 1 : 0, items[current]);
    }

    return { visible, more: items.filter((item) => !visible.includes(item)) };
});

// The room and the controls that give way to it are the Layout's; what fit() set on them, so it can be released. A control's floor is a custom property the
// Layout applies as min-width only from lg (`lg:min-w-[var(--shell-bar-gives-floor,0px)]`), so crossing below lg drops it in the same frame, before any
// ResizeObserver callback runs.
let claimed: { room: HTMLElement | null; controls: HTMLElement[] } = { room: null, controls: [] };
function release(): void {
    if (claimed.room) claimed.room.style.flexBasis = '';
    for (const control of claimed.controls) control.style.removeProperty('--shell-bar-gives-floor');
    claimed = { room: null, controls: [] };
}

// The room asks for every page's width, and each control that gives way may shrink to its minimum: its own width or the token, whichever is smaller.
// The controls shrink ten thousand times as readily as the room (the Layout's flex-shrink), so the flexbox takes the pages' width from them first and from
// the room only once they are at their minimum; then the room is short of the pages and the split below sends the rest into More.
function claim(box: HTMLElement, ruler: HTMLElement, total: number): void {
    const room = box.closest<HTMLElement>('[data-shell-nav-room]');
    if (!room || box.getClientRects().length === 0) {
        // Below lg the room is not drawn, and the controls keep their own sizing.
        release();

        return;
    }
    const floor = ruler.querySelector<HTMLElement>('[data-measure-control-min]')?.getBoundingClientRect().width ?? 0;
    const controls = [...(room.parentElement?.querySelectorAll<HTMLElement>(':scope > [data-shell-bar-gives]') ?? [])];
    for (const control of claimed.controls) if (!controls.includes(control)) control.style.removeProperty('--shell-bar-gives-floor');
    room.style.flexBasis = `${total}px`;
    for (const control of controls) {
        // Its own width, read with nothing taken from it.
        control.style.removeProperty('--shell-bar-gives-floor');
        const shrink = control.style.flexShrink;
        control.style.flexShrink = '0';
        const natural = control.getBoundingClientRect().width;
        control.style.flexShrink = shrink;
        control.style.setProperty('--shell-bar-gives-floor', `${Math.min(natural, floor)}px`);
    }
    claimed = { room, controls };
}

function fit(): void {
    const box = root.value;
    const ruler = measure.value;
    if (!box || !ruler) return;
    // The room's fractional width: clientWidth rounds, and a row that fits by under a pixel must still fit.
    const widths = [...ruler.querySelectorAll<HTMLElement>('[data-measure-item]')].map((el) => el.getBoundingClientRect().width);
    const moreWidth = ruler.querySelector<HTMLElement>('[data-measure-more]')?.getBoundingClientRect().width ?? 0;
    // The row's own gap, read from the ruler, so a host's spacing scale counts.
    const gap = Number.parseFloat(getComputedStyle(ruler.firstElementChild as HTMLElement).columnGap) || 0;
    // Every page in the bar: their widths and the gaps between them, none after the last (no More follows it).
    const total = widths.reduce((sum, width) => sum + width, 0) + gap * Math.max(widths.length - 1, 0);
    claim(box, ruler, total);
    // Read after the claim, so the room counts what the controls gave up.
    const available = box.getBoundingClientRect().width;
    if (total <= available + 0.5) {
        shown.value = Number.POSITIVE_INFINITY;

        return;
    }
    let used = moreWidth;
    let count = 0;
    for (const width of widths) {
        if (used + width + gap > available) break;
        used += width + gap;
        count++;
    }
    // The current page takes a place, so it must fit as well as the one it replaces.
    const current = props.navigation.findIndex((item) => item.current);
    const lead = (n: number): number => widths.slice(0, n).reduce((sum, width) => sum + width + gap, 0);
    if (current >= count) {
        // The first count - 1 pages, then the current one, then More.
        while (count > 1 && moreWidth + lead(count - 1) + widths[current] + gap > available) count--;
        count = Math.max(count, 1);
    }
    shown.value = count;
}

// The box and the ruler exist only while there are pages (v-if), so the observer follows them: a bar that mounts with no navigation, or loses and regains
// it, observes the elements that are there now and measures again.
let observer: ResizeObserver | null = null;
// Never written in the observer's own callback (a ResizeObserver loop): fit() writes the room's flex-basis and the controls' floor, so it runs once in the
// next frame. The floor below lg needs no frame: the Layout applies it only under the lg media query.
let frame = 0;
function schedule(): void {
    if (typeof requestAnimationFrame === 'undefined') return fit();
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
        frame = 0;
        fit();
    });
}
watch(
    [root, measure],
    ([box, ruler]) => {
        observer?.disconnect();
        observer = null;
        if (!box || !ruler) {
            release();

            return;
        }
        observer = new ResizeObserver(schedule);
        observer.observe(box);
        if (ruler.firstElementChild) observer.observe(ruler.firstElementChild);
        fit();
    },
    { flush: 'post' },
);
onMounted(() => fit());
onBeforeUnmount(() => {
    observer?.disconnect();
    if (frame) cancelAnimationFrame(frame);
    release();
});
watch(
    () => props.navigation,
    async () => {
        await nextTick();
        fit();
    },
    { deep: true },
);

// The pages take the room the Layout leaves between the switcher and the controls (a min-w-0 flex-1 box), so search and the account menu stay in the bar.
const linkClass = (item: { current?: boolean }) => [
    'relative flex min-h-11 shrink-0 items-center rounded-md px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-shell-bar-foreground/40 focus-visible:outline-none',
    item.current ? 'bg-shell-bar-foreground/10 text-shell-bar-foreground after:absolute after:inset-x-3 after:-bottom-2.5 after:h-[3px] after:rounded-full after:bg-[var(--brand-marker,var(--shell-bar-foreground))]' : 'text-shell-bar-foreground/70 hover:bg-shell-bar-foreground/10 hover:text-shell-bar-foreground',
];
const moreClass =
    'flex min-h-11 shrink-0 items-center gap-1 rounded-md px-3 text-sm font-medium whitespace-nowrap text-shell-bar-foreground/70 transition-colors hover:bg-shell-bar-foreground/10 hover:text-shell-bar-foreground focus-visible:ring-2 focus-visible:ring-shell-bar-foreground/40 focus-visible:outline-none';
</script>
<!-- The current page: full-strength text on a tinted pill and a 3px marker under it, so it stands apart from the 70% links whatever the brand marker's colour. -->
<template>
    <div v-if="navigation.length" ref="root" class="relative flex w-full min-w-0 items-center" data-shell-nav-box>
        <nav :aria-label="label" class="flex min-w-0 items-center gap-0.5" data-shell-nav>
            <Link v-for="item in split.visible" :key="item.url" :href="item.url" :aria-current="item.current ? 'page' : undefined" :class="linkClass(item)">
                {{ item.label }}
            </Link>
            <DropdownMenu v-if="split.more.length" :modal="false">
                <DropdownMenuTrigger :class="moreClass" data-shell-nav-more>
                    More<span class="sr-only"> pages</span>
                    <ChevronDown class="size-4" aria-hidden="true" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="min-w-48">
                    <DropdownMenuItem v-for="item in split.more" :key="item.url" as-child>
                        <Link :href="item.url" class="w-full" data-shell-nav-more-item>{{ item.label }}</Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </nav>
        <!-- The ruler: every page and the More button at their real size, never seen or read. -->
        <div ref="measure" aria-hidden="true" inert class="pointer-events-none absolute top-0 left-0 h-0 w-0 overflow-hidden" data-shell-nav-measure><div class="flex w-max items-center gap-0.5">
            <span v-for="item in navigation" :key="item.url" :class="linkClass(item)" data-measure-item>{{ item.label }}</span>
            <span :class="moreClass" data-measure-more>More<ChevronDown class="size-4" aria-hidden="true" /></span>
            </div><span class="block h-0 w-[var(--shell-bar-control-min,10rem)]" data-measure-control-min></span>

        </div>
    </div>
</template>
