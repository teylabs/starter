<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { UserMenu, WorkspaceSwitcher } from '@kit/shell';
import { Breadcrumbs, PasskeyOffer, Toaster } from '@kit/ui';
import { computed, provide, useSlots } from 'vue';
import GlobalSearch from './Partials/GlobalSearch.vue';
import PrimaryNav from './Partials/PrimaryNav.vue';
import FocusShell from './Partials/FocusShell.vue';
import type { FocusShellShape } from './Partials/focusShell';
import ShellDrawer from './Partials/ShellDrawer.vue';
import SubNav from './Partials/SubNav.vue';
import Wordmark from './Partials/Wordmark.vue';

/*
 * The shell for pages under pages/Workspace: a header, never a sidebar. The product symbol (the platform's logomark, never a workspace's; no name text) and the workspace switcher sit at the left (always, from the server's `shell.workspace` and `shell.switcher`; the shell decides nothing), then the workspace's pages with one active mark (Settings is one of them, a landing page), then the user menu on the right. Below `lg` a drawer holds the same items. The app
 * owns its mark (`mark` slot), what the account menu adds (`extra`, rendered in the dropdown and the drawer with a `place` of `menu` or `drawer`, or `drawer-extra` for the drawer alone), the switcher and user menu themselves (`workspace-switcher`, `user-menu`: the kit's partials by default, with the same props), header controls (`actions`, in the header and the drawer), controls that stay in the bar at every width
 * (`bar`, rendered once and never in the drawer: a compact control such as a search opener, which shrinks before the switcher and the drawer button do), the workspace's
 * pages themselves (`nav`, with `navigation` and `label`: replaces PrimaryNav at every width, one live copy, and the drawer then lists no pages), the drawer's page list (`drawer-nav`, same props: in place of the kit's pages, usually paired with `nav`), the footer and the
 * width (`widthClass`); the chrome comes from the server's `layout` props. Opt-ins: `wordmarkLabel` names the wordmark link (a tenant's name, say; the product's
 * by default), `guestHeader` draws the header for a guest too (no user menu, no drawer), `breadcrumbs="bar"` draws the trail as a full-width bar under the header
 * (the whole trail, the current page included; <Page> then draws none), and the page ground is `--shell-ground` (the theme's background unless
 * the host sets it). For a host's own root shell: `toaster` false leaves the toasts to the host's single Toaster, `documentTitle` false leaves the <title> to the
 * pages' own <Head>, and `fill` makes an editor page (a Workbench) the viewport's height with no page scroll: the content area fills what the header leaves, unpadded
 * and overflow hidden, and the page scrolls its own panes. Each defaults to today's behaviour. With no pages to list (no `nav` or `drawer-nav` slot and no navigation) there is no drawer: the account menu stays in the bar at every width. A module's pages (the server's `shell.subNavigation`) show as the SubNav strip under the header.
 */
const props = withDefaults(defineProps<{ shell: Tey.Kit.Features.Shell.Data.ShellData | FocusShellShape; widthClass?: string; wordmarkLabel?: string; guestHeader?: boolean; breadcrumbs?: 'page' | 'bar'; toaster?: boolean; documentTitle?: boolean; fill?: boolean }>(), { widthClass: 'max-w-7xl', wordmarkLabel: undefined, guestHeader: false, breadcrumbs: 'page', toaster: true, documentTitle: true, fill: false });
const slots = useSlots();
// The drawer holds the pages below lg; with none to hold it is left out, and the account menu and actions stay in the bar.
// A focus shell (a signed-in page outside any workspace: welcome steps, no workspace yet) renders as FocusShell; everything below reads the app shell.
const focus = computed((): FocusShellShape | null => (props.shell.kind === 'focus' ? (props.shell as FocusShellShape) : null));
const app = computed(() => props.shell as Tey.Kit.Features.Shell.Data.ShellData);
const drawer = computed(() => !focus.value && Boolean(app.value.user) && (Boolean(slots.nav) || Boolean(slots['drawer-nav']) || (app.value.navigation?.length ?? 0) > 0));
// The document title comes from the page's `layout` (composed on the server), never set per page.
const page = usePage<{ layout?: { documentTitle: string; navigationHeader?: { label: string; url: string } | null }; environment?: { label: string; colour: string } | null }>();
// Pages in this shell render their heading (the layout's `title`) and actions inside their <Page>, so it aligns with the content column.
provide('kit.shell', true);
// Where the trail is drawn: by each <Page> above its heading (`page`), or here as a full-width bar under the header (`bar`), the current page included.
provide('kit.shell.breadcrumbs', computed(() => props.breadcrumbs));
</script>
<template>
    <Head v-if="props.documentTitle" :title="page.props.layout?.documentTitle" />
    <FocusShell v-if="focus" :shell="focus" :toaster="props.toaster"><slot /></FocusShell>
    <div v-else :class="['flex flex-col bg-[var(--shell-ground,var(--color-background))] text-foreground', props.fill ? 'h-svh overflow-hidden' : 'min-h-svh']" :data-shell-fill="props.fill || undefined">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:ring-2 focus:ring-ring">Skip to content</a>
        <div v-if="app.user || props.guestHeader" class="sticky top-0 z-(--z-header)" data-shell-top>
            <!-- Outside production (the server decides; null there): a 2px edge in the environment's colour, with its name for a screen reader. It takes no space beyond its 2px. -->
            <div v-if="page.props.environment" class="h-0.5 w-full bg-(--kit-environment)" :style="{ '--kit-environment': page.props.environment.colour }" data-environment-edge><span class="sr-only">{{ page.props.environment.label }} environment</span></div>
            <header class="border-b border-shell-bar-border bg-shell-bar text-shell-bar-foreground backdrop-blur-(--shell-bar-blur)" data-shell-bar>
                <div :class="['mx-auto flex h-16 min-w-0 items-center gap-2 px-4 sm:px-6 lg:px-8', props.widthClass]">
                    <!-- The product lockup (Partials/Wordmark, shared with the GuestShell); the mark slot replaces its symbol. -->
                    <Wordmark :product="app.product" :label="props.wordmarkLabel"><template v-if="$slots.mark" #default><slot name="mark" /></template></Wordmark>
                    <!-- With no workspace there is nothing to switch: the product's name stands where the switcher would, with no caret (the server decides: no workspace and no switcher). With the lockup in the bar it already shows the name, so this stays for assistive tech and the data-shell-product-name contract. -->
                    <span v-if="!app.workspace && !app.switcher" :class="app.product.lockup ? 'sr-only' : 'min-w-0 flex-1 truncate text-sm font-medium lg:flex-none'" data-shell-product-name>{{ app.product.name }}</span>
                    <!-- The workspace switcher is always there: with one workspace its menu is just Workspace settings (when the server sends it), with two or more it lists them too. -->
                    <!-- The `workspace-switcher` slot replaces it; its props are the switcher's own, plus the bar's sizing `class`. -->
                    <template v-if="app.workspace || app.switcher">
                        <slot name="workspace-switcher" :workspace="app.workspace" :switcher="app.switcher" :account="app.account" :class="['flex-1 lg:max-w-72 lg:flex-none', $slots.bar ? 'min-w-24' : 'min-w-0']">
                            <WorkspaceSwitcher :workspace="app.workspace" :switcher="app.switcher" :account="app.account" :class="['flex-1 lg:max-w-72 lg:flex-none', $slots.bar ? 'min-w-24' : 'min-w-0']" />
                        </slot>
                    </template>
                    <!-- The nav slot replaces PrimaryNav at every width (an app's own module switcher): one live copy, and the drawer then lists no pages. -->
                    <div v-if="$slots.nav" class="ml-3 flex min-w-0 shrink items-center" data-shell-nav-slot>
                        <slot name="nav" :navigation="app.navigation" :label="app.workspace?.name ?? app.product.name" />
                    </div>
                    <div v-else class="ml-3 hidden min-w-0 flex-1 items-center lg:flex" data-shell-nav-room>
                        <PrimaryNav :navigation="app.navigation" :label="app.workspace?.name ?? app.product.name" />
                    </div>
                    <!-- The bar slot: one live copy at every width, so its state, focus and listeners survive crossing lg. It may shrink; the controls after it do not. -->
                    <div v-if="$slots.bar" class="ml-auto flex min-w-0 items-center gap-2" data-shell-bar-slot>
                        <slot name="bar" />
                    </div>
                    <!-- Global search: only when the server sends it (the viewer may search this workspace). -->
                    <div v-if="app.search" :class="['flex min-w-0 items-center', $slots.bar ? '' : 'ml-auto']" data-shell-search>
                        <GlobalSearch :search="app.search" />
                    </div>
                    <div :class="['flex shrink-0 items-center gap-2', $slots.bar || app.search ? '' : 'ml-auto']" data-shell-actions>
                        <div :class="['items-center gap-2', drawer ? 'hidden lg:flex' : 'flex']">
                            <slot name="actions" />
                            <!-- The `user-menu` slot replaces it, with the menu's own props. -->
                            <template v-if="app.user && app.links.sign_out">
                                <slot name="user-menu" :user="app.user" :sign-out-url="app.links.sign_out">
                                    <UserMenu :user="app.user" :sign-out-url="app.links.sign_out">
                                        <!-- `extra` is rendered in the account dropdown and in the phone drawer: its `place` says which (menu items there, plain links here). -->
                                        <template #extra><slot name="extra" place="menu" /></template>
                                    </UserMenu>
                                </slot>
                            </template>
                        </div>
                        <div v-if="drawer" class="lg:hidden">
                            <!-- The drawer-nav slot puts the app's own page list in the phone drawer, in place of the kit's pages; without it the drawer lists them unless `nav` is used. -->
                            <ShellDrawer :shell="app" :pages="!$slots.nav">
                                <template v-if="$slots['drawer-nav']" #nav><slot name="drawer-nav" :navigation="app.navigation" :label="app.workspace?.name ?? app.product.name" /></template>
                                <template #actions><slot name="actions" /></template>
                                <!-- The drawer takes `drawer-extra` when given, else `extra` with place="drawer". -->
                                <template #extra><slot v-if="$slots['drawer-extra']" name="drawer-extra" /><slot v-else name="extra" place="drawer" /></template>
                            </ShellDrawer>
                        </div>
                    </div>
                </div>
            </header>
            <!-- The trail as a bar, unless the page has a "Back to <parent>" strip, which replaces the trail. -->
            <div v-if="props.breadcrumbs === 'bar' && app.user && !page.props.layout?.navigationHeader" class="border-b border-shell-subbar-border bg-shell-subbar text-foreground backdrop-blur-(--shell-subbar-blur)" data-shell-breadcrumbs>
                <div :class="['mx-auto flex h-10 min-w-0 items-center px-4 sm:px-6 lg:px-8', props.widthClass]">
                    <Breadcrumbs bar />
                </div>
            </div>
        </div>
        <!-- The active module's pages: a strip under the header that scrolls with the page, in the subbar's tones and the content measure. -->
        <div v-if="app.user && app.subNavigation?.items.length" class="border-b border-shell-subbar-border bg-shell-subbar text-foreground">
            <div :class="['mx-auto min-w-0 px-4 sm:px-6 lg:px-8', props.widthClass]">
                <SubNav :label="app.subNavigation.label" :items="app.subNavigation.items" />
            </div>
        </div>
        <!-- A nested page's way up one level (the page's `layout.navigationHeader`: a breakout page or an account page): a strip under the header that scrolls with the page, in place of the trail, tinted from --shell-band. -->
        <div v-if="page.props.layout?.navigationHeader" class="border-b border-shell-band-border bg-shell-band text-shell-band-foreground" data-navigation-header>
            <div :class="['mx-auto flex min-h-11 min-w-0 items-center px-4 sm:px-6 lg:px-8', props.widthClass]">
                <Link :href="page.props.layout.navigationHeader.url" class="inline-flex min-h-9 min-w-0 items-center gap-2 rounded-md text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"><ArrowLeft class="size-4 shrink-0" aria-hidden="true" /><span class="min-w-0 truncate">{{ page.props.layout.navigationHeader.label }}</span></Link>
            </div>
        </div>
        <main id="main" :class="props.fill ? 'flex min-h-0 flex-1 flex-col overflow-hidden' : 'flex-1'">
            <div :class="props.fill ? ['mx-auto flex min-h-0 w-full flex-1 flex-col gap-6', props.widthClass] : ['mx-auto flex w-full flex-col gap-6 px-4 pt-4 pb-8 sm:px-6 sm:pt-5 sm:pb-10 lg:px-8', props.widthClass]" data-shell-content>
                <!-- After an invited sign-up, where the app has passkeys: "Add a passkey for next time" (session-ui's PasskeyOffer). -->
                <!-- The content column gaps the offer and the page (gap-6): neither carries a margin. -->
                <PasskeyOffer />
                <slot />
            </div>
        </main>
        <slot name="footer" />
        <!-- The one toast renderer: it shows what the server flashed, where kit.toasts.position puts it. `toaster` false: the host's own root shell mounts it. -->
        <Toaster v-if="props.toaster" />
    </div>
</template>
