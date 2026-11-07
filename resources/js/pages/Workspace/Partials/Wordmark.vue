<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/*
 * The product lockup, shared by the signed-in bar and the GuestShell: the platform's SYMBOL (no name text by default; the name is in the document title, the guest
 * card and the footer), linking home and named for the product. A fixed box (--shell-mark-height, capped by --shell-mark-max-width) with the logomark scaling
 * inside it (`logomark_dark` under .dark), or a monogram without one. The brand `lockup` option adds the wordmark beside the mark (`wordmark_dark` under .dark),
 * or the product's name where there is no wordmark image. `tone` is where it sits: `bar` takes the bar's mark treatment and focus colour, `page` (the guest page)
 * the page's. `label` names the link in place of the product (a tenant-branded app's own name). The default slot replaces the symbol (the host's own mark).
 */
const props = withDefaults(
    defineProps<{ product: { name: string; url: string; logomark: string | null; logomark_dark?: string | null; wordmark?: string | null; wordmark_dark?: string | null; lockup?: boolean }; tone?: 'bar' | 'page'; label?: string }>(),
    { tone: 'bar', label: undefined },
);
</script>
<template>
    <Link :href="props.product.url" :aria-label="props.label || props.product.name" :class="['flex h-(--shell-mark-height) shrink-0 items-center rounded-md px-1 focus-visible:ring-2 focus-visible:outline-none', props.product.lockup ? 'max-w-none' : 'max-w-[min(var(--shell-mark-max-width),7rem)] sm:max-w-(--shell-mark-max-width)', props.tone === 'bar' ? 'focus-visible:ring-shell-bar-foreground/40' : 'focus-visible:ring-ring']" data-shell-wordmark>
        <slot><template v-if="props.product.logomark"><img :src="props.product.logomark" alt="" :class="['h-full w-auto max-w-full object-contain', props.tone === 'bar' ? 'brightness-(--shell-mark-brightness) invert-(--shell-mark-invert)' : '', props.product.logomark_dark && 'dark:hidden']" /><img v-if="props.product.logomark_dark" :src="props.product.logomark_dark" alt="" :class="['hidden h-full w-auto max-w-full object-contain dark:block', props.tone === 'bar' ? 'brightness-(--shell-mark-brightness) invert-(--shell-mark-invert)' : '']" /></template><span v-else :class="['flex size-6 items-center justify-center rounded-md text-xs font-semibold', props.tone === 'bar' ? 'bg-shell-bar-foreground/10' : 'bg-foreground/10']" aria-hidden="true">{{ props.product.name.slice(0, 1) }}</span></slot>
        <span v-if="props.product.lockup" class="ml-2 hidden h-full min-w-0 items-center sm:flex" data-shell-lockup>
            <template v-if="props.product.wordmark"><img :src="props.product.wordmark" alt="" :class="['h-full w-auto object-contain', props.product.wordmark_dark && 'dark:hidden']" /><img v-if="props.product.wordmark_dark" :src="props.product.wordmark_dark" alt="" class="hidden h-full w-auto object-contain dark:block" /></template>
            <span v-else class="truncate text-sm font-semibold">{{ props.product.name }}</span>
        </span>
    </Link>
</template>
