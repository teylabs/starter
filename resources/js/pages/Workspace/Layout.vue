<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { UserMenu, WorkspaceSwitcher } from '@kit/shell';
import { Toaster } from '@kit/ui';
import { provide } from 'vue';
import Breadcrumbs from './Partials/Breadcrumbs.vue';
import GlobalSearch from './Partials/GlobalSearch.vue';
import PrimaryNav from './Partials/PrimaryNav.vue';
import ShellDrawer from './Partials/ShellDrawer.vue';
import SubNav from './Partials/SubNav.vue';
import Wordmark from './Partials/Wordmark.vue';

/*
 * The shell for pages under pages/Workspace: a header, never a sidebar. The product symbol (the platform's logomark, never a workspace's; no name text) and the workspace switcher sit at the left (always, from the server's `shell.workspace` and `shell.switcher`; the shell decides nothing), then the workspace's pages with one active mark (Settings is one of them, a landing page), then the user menu on the right. Below `lg` a drawer holds the same items. The app
 * owns its mark (`mark` slot), what the account menu adds (`extra`), the switcher and user menu themselves (`workspace-switcher`, `user-menu`: the kit's partials by default, with the same props), header controls (`actions`, in the header and the drawer), controls that stay in the bar at every width
 * (`bar`, rendered once and never in the drawer: a compact control such as a search opener, which shrinks before the switcher and the drawer button do), the footer and the
 * width (`widthClass`); the chrome comes from the server's `layout` props. A module's pages (the server's `shell.subNavigation`) show as the SubNav strip under the header.
 */
const props = withDefaults(defineProps<{ shell: Tey.Kit.Features.Shell.Data.ShellData; widthClass?: string }>(), { widthClass: 'max-w-7xl' });
// The document title comes from the page's `layout` (composed on the server), never set per page.
const page = usePage<{ layout?: { documentTitle: string; navigationHeader?: { label: string; url: string } | null }; environment?: { label: string; colour: string } | null }>();
// Pages in this shell render their heading (the layout's `title`) and actions inside their <Page>, so it aligns with the content column.
provide('kit.shell', true);
</script>
<template>
    <Head :title="page.props.layout?.documentTitle" />
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:ring-2 focus:ring-ring">Skip to content</a>
        <div v-if="props.shell.user" class="sticky top-0 z-(--z-header)" data-shell-top>
            <!-- Outside production (the server decides; null there): a 2px edge in the environment's colour, with its name for a screen reader. It takes no space beyond its 2px. -->
            <div v-if="page.props.environment" class="h-0.5 w-full" :style="{ backgroundColor: page.props.environment.colour }" data-environment-edge><span class="sr-only">{{ page.props.environment.label }} environment</span></div>
            <header class="border-b border-shell-bar-border bg-shell-bar text-shell-bar-foreground backdrop-blur-(--shell-bar-blur)" data-shell-bar>
                <div :class="['mx-auto flex h-16 min-w-0 items-center gap-2 px-4 sm:px-6 lg:px-8', props.widthClass]">
                    <!-- The product lockup (Partials/Wordmark, shared with the GuestShell); the mark slot replaces its symbol. -->
                    <Wordmark :product="props.shell.product"><template v-if="$slots.mark" #default><slot name="mark" /></template></Wordmark>
                    <!-- With no workspace there is nothing to switch: the product's name stands where the switcher would, with no caret (the server decides: no workspace and no switcher). -->
                    <span v-if="!props.shell.workspace && !props.shell.switcher && !props.shell.product.lockup" class="min-w-0 flex-1 truncate text-sm font-medium lg:flex-none" data-shell-product-name>{{ props.shell.product.name }}</span>
                    <!-- The workspace switcher is always there: with one workspace its menu is just Workspace settings (when the server sends it), with two or more it lists them too. -->
                    <!-- The `workspace-switcher` slot replaces it; its props are the switcher's own, plus the bar's sizing `class`. -->
                    <template v-if="props.shell.workspace || props.shell.switcher">
                        <slot name="workspace-switcher" :workspace="props.shell.workspace" :switcher="props.shell.switcher" :account="props.shell.account" :class="['flex-1 lg:max-w-72 lg:flex-none', $slots.bar ? 'min-w-24' : 'min-w-0']">
                            <WorkspaceSwitcher :workspace="props.shell.workspace" :switcher="props.shell.switcher" :account="props.shell.account" :class="['flex-1 lg:max-w-72 lg:flex-none', $slots.bar ? 'min-w-24' : 'min-w-0']" />
                        </slot>
                    </template>
                    <div class="ml-3 hidden shrink-0 items-center gap-0.5 lg:flex">
                        <PrimaryNav :navigation="props.shell.navigation" :label="props.shell.workspace?.name ?? props.shell.product.name" />
                    </div>
                    <!-- The bar slot: one live copy at every width, so its state, focus and listeners survive crossing lg. It may shrink; the controls after it do not. -->
                    <div v-if="$slots.bar" class="ml-auto flex min-w-0 items-center gap-2" data-shell-bar-slot>
                        <slot name="bar" />
                    </div>
                    <!-- Global search: only when the server sends it (the viewer may search this workspace). -->
                    <div v-if="props.shell.search" :class="['flex min-w-0 items-center', $slots.bar ? '' : 'ml-auto']" data-shell-search>
                        <GlobalSearch :search="props.shell.search" />
                    </div>
                    <div :class="['flex shrink-0 items-center gap-2', $slots.bar || props.shell.search ? '' : 'ml-auto']" data-shell-actions>
                        <div class="hidden items-center gap-2 lg:flex">
                            <slot name="actions" />
                            <!-- The `user-menu` slot replaces it, with the menu's own props. -->
                            <template v-if="props.shell.user && props.shell.links.sign_out">
                                <slot name="user-menu" :user="props.shell.user" :sign-out-url="props.shell.links.sign_out">
                                    <UserMenu :user="props.shell.user" :sign-out-url="props.shell.links.sign_out">
                                        <template #extra><slot name="extra" /></template>
                                    </UserMenu>
                                </slot>
                            </template>
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
        <!-- The active module's pages: a strip under the header that scrolls with the page, in the subbar's tones and the content measure. -->
        <div v-if="props.shell.user && props.shell.subNavigation?.items.length" class="border-b border-shell-subbar-border bg-shell-subbar text-foreground">
            <div :class="['mx-auto min-w-0 px-4 sm:px-6 lg:px-8', props.widthClass]">
                <SubNav :label="props.shell.subNavigation.label" :items="props.shell.subNavigation.items" />
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
        <!-- The one toast renderer: it shows what the server flashed, where kit.toasts.position puts it. -->
        <Toaster />
    </div>
</template>
