<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { nextTick, onMounted, ref, watch } from 'vue';

/*
 * The active module's pages (the server's `shell.subNavigation`): a strip of ordinary links under the header, in the server's order,
 * one marked current by the server (never guessed here). It looks like tabs and navigates pages, so no tab roles and no arrow keys.
 * On a phone it stays one line that scrolls sideways; the current link is brought into view in the strip only, never moving focus
 * or the page. It is not the "Back to parent" strip (`layout.navigationHeader`), and either may be absent.
 */
const props = defineProps<{ label: string; items: NonNullable<Tey.Kit.Features.Shell.Data.ShellData['subNavigation']>['items'] }>();
const strip = ref<HTMLElement | null>(null);

// Scrolls the strip, only as far as needed, so the link and its focus ring are inside it.
function reveal(link: HTMLElement | null, smooth: boolean): void {
    const list = strip.value;
    if (!list || !link) {
        return;
    }
    const left = link.offsetLeft;
    const right = left + link.offsetWidth;
    const target = left < list.scrollLeft ? left : right > list.scrollLeft + list.clientWidth ? right - list.clientWidth : null;
    if (target !== null) {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        list.scrollTo({ left: target, behavior: smooth && !reduced ? 'smooth' : 'auto' });
    }
}
const revealCurrent = (smooth: boolean) => nextTick(() => reveal(strip.value?.querySelector<HTMLElement>('[aria-current="page"]') ?? null, smooth));
onMounted(() => revealCurrent(false));
watch(() => props.items, () => revealCurrent(true));
</script>
<template>
    <!-- The Layout draws the band (subbar tokens, one bottom border) and the content measure around it; this is the strip itself. -->
    <nav v-if="props.items.length" :aria-label="props.label" class="-mx-3 min-w-0" data-shell-subnav>
        <ul ref="strip" class="relative flex overflow-x-auto overscroll-x-contain">
            <li v-for="item in props.items" :key="item.url" class="shrink-0">
                <Link :href="item.url" :aria-current="item.current ? 'page' : undefined" :class="['inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-medium whitespace-nowrap focus-visible:rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset', item.current ? 'border-foreground text-foreground' : 'border-transparent text-foreground/70 hover:border-foreground/30 hover:text-foreground']" @focus="reveal($event.currentTarget as HTMLElement, false)">{{ item.label }}</Link>
            </li>
        </ul>
    </nav>
</template>
