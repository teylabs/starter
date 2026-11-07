<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { AppearanceToggle, Toaster } from '@kit/ui';
import { computed } from 'vue';
import Wordmark from './Wordmark.vue';

/*
 * The public shell: sessionless public pages (a landing, help, legal, an error) in one chrome, built like the GuestShell and sharing its Wordmark. A skip link to
 * #content; a header with the product lockup, a `nav` slot (the page's own links) and a call to action (the `cta` slot, else the kit's: "Sign in" for a guest,
 * "Open your workspace" for a signed-in viewer, from the shared shell's kind and nothing else); `main#content` at `width` prose (a reading measure), wide or full;
 * a footer with the shared `shell.footer` (identity, links, credit: the brand config). `tone="brand"` puts the header and footer on the primary colour.
 * It mounts the kit Toaster, as the app shell does, so a public form's redirect toast (a contact form's "Thanks, we'll be in touch") shows; `toaster` false leaves
 * it to the host (a page that mounts its own). It reads no user, workspace or permission and sets no tracking: a cacheable page. Page meta is PublicMeta's. kit.home stays optional: a host keeps its own
 * landing and uses this shell around it.
 */
type Product = { name: string; url: string; logomark: string | null; logomark_dark?: string | null; wordmark?: string | null; wordmark_dark?: string | null; lockup?: boolean };
type Footer = { identity: string; credit: string; links: { label: string; url: string }[] };
// The shell knows no session route: the host passes the CTA's URLs (`sign-in-url`, `workspace-url`), and the CTA shows only when the one for the viewer is given.
// A branded site (all opt-in): `width="site"` (max-w-6xl) with `align-bars` puts the header and footer rows on the same measure; `tone="bar"` colours the header with
// the shell's own --shell-bar tokens (not --primary); `pin-footer` false lets the footer flow after short content; `padded` false drops main's padding; `footer-links`
// and `footer-note` (with the `footer-mark` slot) give the footer without the session's shared shell (and with no product the `mark` slot links to `home-url`, named
// `home-label`); `theme="light"` keeps a page with no dark styling light.
const props = withDefaults(defineProps<{
    width?: 'prose' | 'site' | 'wide' | 'full'; tone?: 'default' | 'brand' | 'bar'; cta?: boolean; signInUrl?: string | null; workspaceUrl?: string | null; appearance?: boolean;
    product?: Product | null; toaster?: boolean; alignBars?: boolean; pinFooter?: boolean; padded?: boolean; footerLinks?: { label: string; url: string }[] | null;
    footerNote?: string | null; theme?: 'system' | 'light'; homeUrl?: string; homeLabel?: string;
}>(), {
    width: 'wide', tone: 'default', cta: true, signInUrl: null, workspaceUrl: null, appearance: true, product: null, toaster: true, alignBars: false, pinFooter: true,
    padded: true, footerLinks: null, footerNote: null, theme: 'system', homeUrl: '/', homeLabel: 'Home',
});
const page = usePage<{ shell?: { kind?: string; product?: Product; footer?: Footer } }>();
const product = computed(() => props.product ?? page.props.shell?.product ?? null);
// The host's own footer (props) wins; else the shared shell's, when session-ui shares it.
const footer = computed<Footer | null>(() => (props.footerLinks || props.footerNote ? { identity: props.footerNote ?? '', credit: '', links: props.footerLinks ?? [] } : page.props.shell?.footer ?? null));
// The one auth-aware bit: whether the shared shell is a guest's. Nothing else about the viewer is read.
const signedIn = computed(() => Boolean(page.props.shell?.kind) && page.props.shell?.kind !== 'guest');
const ctaUrl = computed(() => (signedIn.value ? props.workspaceUrl : props.signInUrl) ?? null);
// Whole class strings (Tailwind scans the source).
const measures = { prose: 'max-w-3xl', site: 'max-w-6xl', wide: 'max-w-7xl', full: 'max-w-none' } as const;
const brand = computed(() => props.tone === 'brand');
const bar = computed(() => props.tone === 'bar');
// The header and footer rows: the wide measure, or main's own with align-bars.
const rows = computed(() => (props.alignBars ? [measures[props.width], 'px-6'] : ['max-w-7xl', 'px-4 sm:px-6 lg:px-8']));
</script>
<template>
    <div :class="['flex min-h-svh flex-col bg-background text-foreground', theme === 'light' && 'kit-theme-light']" :data-tone="tone" :data-theme="theme" data-public-shell>
        <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:text-foreground focus:ring-2 focus:ring-ring" data-public-skip>Skip to content</a>
        <header :class="[brand ? 'bg-primary text-primary-foreground' : bar ? 'border-b border-transparent bg-(--shell-bar) text-(--shell-bar-foreground)' : 'border-b border-border']" data-public-header>
            <div :class="['mx-auto flex min-h-16 w-full min-w-0 flex-wrap items-center gap-x-4 gap-y-2 py-2', rows]">
                <Wordmark v-if="product" :product="product" :tone="brand || bar ? 'bar' : 'page'"><template v-if="$slots.mark" #default><slot name="mark" /></template></Wordmark>
                <!-- No product (a host without session-ui's shared shell): the mark alone links home, named by home-label. -->
                <Link v-else-if="$slots.mark" :href="homeUrl" :aria-label="homeLabel" class="inline-flex min-h-11 shrink-0 items-center focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-public-home><slot name="mark" /></Link>
                <nav v-if="$slots.nav" aria-label="Site" class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1 text-sm" data-public-nav><slot name="nav" /></nav>
                <div class="ml-auto flex shrink-0 items-center gap-2">
                    <slot name="cta">
                        <Link v-if="cta && ctaUrl" :href="ctaUrl" :class="['inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', brand ? 'bg-primary-foreground text-primary hover:bg-primary-foreground/90' : 'bg-primary text-primary-foreground hover:bg-primary/90']" :data-public-cta="signedIn ? 'workspace' : 'sign-in'">{{ signedIn ? 'Open your workspace' : 'Sign in' }}</Link>
                    </slot>
                    <AppearanceToggle v-if="appearance" :class="brand ? 'text-primary-foreground' : bar ? 'text-(--shell-bar-foreground)' : 'text-foreground'" />
                </div>
            </div>
        </header>
        <main id="content" tabindex="-1" :class="['mx-auto w-full min-w-0 focus-visible:outline-none', pinFooter && 'flex-1', padded && (alignBars ? 'px-6 pt-8 pb-10' : 'px-4 py-8 sm:px-6 sm:py-12 lg:px-8'), measures[width]]" data-public-main>
            <slot />
        </main>
        <footer v-if="footer || $slots.footer || $slots['footer-mark']" :class="[brand ? 'bg-primary text-primary-foreground' : alignBars ? 'text-muted-foreground' : 'border-t border-border text-muted-foreground']" data-public-footer>
            <div :class="['mx-auto flex w-full min-w-0 flex-col gap-3 py-6 text-sm sm:flex-row sm:flex-wrap sm:items-center', alignBars ? 'sm:justify-start sm:py-10' : 'sm:justify-between', rows]">
                <slot name="footer-mark" />
                <p v-if="footer?.identity" class="m-0">{{ footer.identity }}</p>
                <nav v-if="footer?.links?.length" aria-label="Footer" data-public-footer-links>
                    <ul class="m-0 flex list-none flex-wrap gap-x-4 gap-y-1 p-0">
                        <li v-for="link in footer.links" :key="link.url"><a :href="link.url" class="inline-flex min-h-11 items-center underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:min-h-0">{{ link.label }}</a></li>
                    </ul>
                </nav>
                <slot name="footer" />
                <p v-if="footer?.credit" class="m-0 text-xs" data-public-credit>{{ footer.credit }}</p>
            </div>
        </footer>
        <Toaster v-if="toaster" />
    </div>
</template>
