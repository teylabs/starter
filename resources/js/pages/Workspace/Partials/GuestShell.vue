<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { AppearanceToggle, Toaster } from '@kit/ui';
import { computed } from 'vue';
import Wordmark from './Wordmark.vue';

/*
 * The guest shell: every page a signed-out visitor reaches (sign in, verification, two-factor, password pages, public content) and a guest's error page.
 * It needs nothing personal: no ShellData, user, workspace or auth route, so it serves a core-only host, a cacheable public page and an error that keeps its
 * real status alike. The host's one layout callback picks it (auth pages, and any page whose shared user is null); pages declare no layout.
 *
 * It owns the lockup (the bar's Wordmark, on the page's tone) above a card, the gutters and the measure (`narrow` for a short form, `wide` for public content),
 * the title and description (from the page's ViewModel, through the host), a labelled way back to the site (`backUrl`, `backLabel`: the host's, never guessed
 * from a route) and the appearance switch, for an app whose dark theme is designed (`appearance`). Slots: the card body (default), `footer` for secondary
 * links under the card ("Create an account") and `mark` for the host's own symbol. The product comes from `product`, else the server's shared `shell.product`.
 */
type Product = { name: string; url: string; logomark: string | null };
const props = withDefaults(defineProps<{ title?: string; description?: string; width?: 'narrow' | 'wide'; backUrl?: string | null; backLabel?: string; appearance?: boolean; product?: Product | null }>(), { title: undefined, description: undefined, width: 'narrow', backUrl: null, backLabel: 'Back to the site', appearance: true, product: null });
const page = usePage<{ shell?: { product?: Product }; layout?: { documentTitle?: string } }>();
const product = computed(() => props.product ?? page.props.shell?.product ?? null);
</script>
<template>
    <Head v-if="page.props.layout?.documentTitle" :title="page.props.layout.documentTitle" />
    <div class="flex min-h-svh flex-col bg-background text-foreground" data-guest-shell>
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:ring-2 focus:ring-ring">Skip to content</a>
        <header :class="['mx-auto flex h-16 w-full min-w-0 items-center gap-2 px-4 sm:px-6 lg:px-8', props.width === 'wide' ? 'max-w-7xl' : 'max-w-md sm:max-w-lg']">
            <Wordmark v-if="product" :product="product" tone="page"><template v-if="$slots.mark" #default><slot name="mark" /></template></Wordmark>
            <div class="ml-auto flex shrink-0 items-center gap-1">
                <Link v-if="props.backUrl" :href="props.backUrl" class="inline-flex min-h-11 items-center gap-2 rounded-md px-2 text-sm font-medium text-muted-foreground hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-guest-back><ArrowLeft class="size-4 shrink-0" aria-hidden="true" />{{ props.backLabel }}</Link>
                <AppearanceToggle v-if="props.appearance" class="text-foreground" />
            </div>
        </header>
        <main id="main" :class="['mx-auto w-full flex-1 px-4 pt-4 pb-10 sm:px-6 sm:pt-10 lg:px-8', props.width === 'wide' ? 'max-w-7xl' : 'max-w-md sm:max-w-lg']">
            <div class="rounded-lg border border-border bg-card p-6 text-card-foreground shadow-xs sm:p-8" data-guest-card>
                <div v-if="props.title" class="mb-6 space-y-2">
                    <h1 class="font-serif text-2xl font-(weight:--font-display-weight) tracking-tight">{{ props.title }}</h1>
                    <p v-if="props.description" class="text-sm text-muted-foreground">{{ props.description }}</p>
                </div>
                <slot />
            </div>
            <div v-if="$slots.footer" class="mt-6 text-center text-sm text-muted-foreground" data-guest-footer><slot name="footer" /></div>
        </main>
        <!-- The one toast renderer: it shows what the server flashed, where kit.toasts.position puts it. -->
        <Toaster />
    </div>
</template>
