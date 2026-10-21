<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { provide } from 'vue';
import Breadcrumbs from './Partials/Breadcrumbs.vue';
import PrimaryNav from './Partials/PrimaryNav.vue';
import ShellDrawer from './Partials/ShellDrawer.vue';
import UserMenu from './Partials/UserMenu.vue';
import WorkspaceSwitcher from './Partials/WorkspaceSwitcher.vue';

/*
 * The shell for pages under pages/Workspace: a header, never a sidebar. The product symbol (the platform's logomark, never a workspace's; no name text) and the workspace switcher sit at the left (always, from the server's `shell.workspace` and `shell.switcher`; the shell decides nothing), then the workspace's pages with one active mark (Settings is one of them, a landing page), then the user menu on the right. Below `lg` a drawer holds the same items. The app
 * owns its mark (`mark` slot), what the account menu adds (`extra`), header controls (`actions`, in the header and the drawer), the footer and the width (`widthClass`); the
 * chrome comes from the server's `layout` props.
 */
const props = withDefaults(defineProps<{ shell: Tey.Kit.Features.SessionUi.Data.ShellData; widthClass?: string }>(), { widthClass: 'max-w-7xl' });
// The document title comes from the page's `layout` (composed on the server), never set per page.
const page = usePage<{ layout?: { documentTitle: string; navigationHeader?: { label: string; url: string } | null } }>();
// Pages in this shell render their heading (the layout's `title`) and actions inside their <Page>, so it aligns with the content column.
provide('kit.shell', true);
</script>
<template>
    <Head :title="page.props.layout?.documentTitle" />
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:ring-2 focus:ring-ring">Skip to content</a>
        <div v-if="props.shell.user" class="sticky top-0 z-30" data-shell-top>
            <header class="border-b border-shell-bar-border bg-shell-bar text-shell-bar-foreground backdrop-blur-(--shell-bar-blur)" data-shell-bar>
                <div :class="['mx-auto flex h-16 min-w-0 items-center gap-2 px-4 sm:px-6 lg:px-8', props.widthClass]">
                    <!-- The mark slot holds the product SYMBOL only (no name text; the name is in the document title, the guest card and the footer): a fixed box (--shell-mark-height, capped by --shell-mark-max-width), the logomark scaling inside it. -->
                    <Link :href="props.shell.product.url" :aria-label="props.shell.product.name" class="flex h-(--shell-mark-height) max-w-[min(var(--shell-mark-max-width),7rem)] shrink-0 items-center rounded-md px-1 focus-visible:ring-2 focus-visible:ring-shell-bar-foreground/40 focus-visible:outline-none sm:max-w-(--shell-mark-max-width)" data-shell-wordmark>
                        <slot name="mark"><img v-if="props.shell.product.logomark" :src="props.shell.product.logomark" alt="" class="h-full w-auto max-w-full object-contain brightness-(--shell-mark-brightness) invert-(--shell-mark-invert)" /><span v-else class="flex size-6 items-center justify-center rounded-md bg-shell-bar-foreground/10 text-xs font-semibold" aria-hidden="true">{{ props.shell.product.name.slice(0, 1) }}</span></slot>
                    </Link>
                    <!-- The workspace switcher is always there: with one workspace its menu is just Workspace settings (when the server sends it), with two or more it lists them too. -->
                    <WorkspaceSwitcher v-if="props.shell.workspace || props.shell.switcher" :workspace="props.shell.workspace" :switcher="props.shell.switcher" :account="props.shell.account" class="min-w-0 flex-1 lg:max-w-72 lg:flex-none" />
                    <div class="ml-3 hidden shrink-0 items-center gap-0.5 lg:flex">
                        <PrimaryNav :navigation="props.shell.navigation" :label="props.shell.workspace?.name ?? props.shell.product.name" />
                    </div>
                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        <div class="hidden items-center gap-2 lg:flex">
                            <slot name="actions" />
                            <UserMenu v-if="props.shell.user && props.shell.links.sign_out" :user="props.shell.user" :sign-out-url="props.shell.links.sign_out">
                                <template #extra><slot name="extra" /></template>
                            </UserMenu>
                        </div>
                        <div class="lg:hidden">
                            <ShellDrawer :shell="props.shell">
                                <template #actions><slot name="actions" /></template>
                                <template #extra><slot name="extra" /></template>
                            </ShellDrawer>
                        </div>
                    </div>
                </div>
            </header>
            <div class="border-b border-shell-subbar-border bg-shell-subbar text-foreground backdrop-blur-(--shell-subbar-blur)" data-shell-subbar>
                <div :class="['mx-auto flex h-10 min-w-0 items-center px-4 sm:px-6 lg:px-8', props.widthClass]">
                    <Breadcrumbs />
                </div>
            </div>
        </div>
        <!-- A nested page's way up one level (the page's `layout.navigationHeader`): a strip under the header and breadcrumbs that scrolls with the page, tinted from --shell-band. -->
        <div v-if="page.props.layout?.navigationHeader" class="border-b border-shell-band-border bg-shell-band text-shell-band-foreground" data-navigation-header>
            <div :class="['mx-auto flex min-h-11 min-w-0 items-center px-4 sm:px-6 lg:px-8', props.widthClass]">
                <Link :href="page.props.layout.navigationHeader.url" class="inline-flex min-h-9 min-w-0 items-center gap-2 rounded-md text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"><ArrowLeft class="size-4 shrink-0" aria-hidden="true" /><span class="min-w-0 truncate">{{ page.props.layout.navigationHeader.label }}</span></Link>
            </div>
        </div>
        <main id="main" class="flex-1">
            <div :class="['mx-auto w-full px-4 pt-4 pb-8 sm:px-6 sm:pt-5 sm:pb-10 lg:px-8', props.widthClass]" data-shell-content>
                <slot />
            </div>
        </main>
        <slot name="footer" />
    </div>
</template>
