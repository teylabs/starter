<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@kit/ui';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/*
 * The current workspace's pages, with one active mark: aria-current plus an underline on the bar's edge. The nav measures itself: the pages that don't
 * fit the room the bar leaves it go into a "More" menu at the end, in their order. The current page always stays in the bar (the last pages before it
 * step aside instead), so the active mark is never hidden. A hidden copy of every link is measured, so the split never depends on what is shown.
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

function fit(): void {
    const box = root.value;
    const ruler = measure.value;
    if (!box || !ruler) return;
    const available = box.clientWidth;
    const widths = [...ruler.querySelectorAll<HTMLElement>('[data-measure-item]')].map((el) => el.getBoundingClientRect().width);
    const moreWidth = ruler.querySelector<HTMLElement>('[data-measure-more]')?.getBoundingClientRect().width ?? 0;
    // The row's own gap, read from the ruler, so a host's spacing scale counts.
    const gap = Number.parseFloat(getComputedStyle(ruler.firstElementChild as HTMLElement).columnGap) || 0;
    const total = widths.reduce((sum, width) => sum + width + gap, 0);
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

let observer: ResizeObserver | null = null;
onMounted(() => {
    observer = new ResizeObserver(() => fit());
    if (root.value) observer.observe(root.value);
    if (measure.value?.firstElementChild) observer.observe(measure.value.firstElementChild);
    fit();
});
onBeforeUnmount(() => observer?.disconnect());
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
            </div>
        </div>
    </div>
</template>
