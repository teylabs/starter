<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from './Partials/Breadcrumbs.vue';
import EnvPill from './Partials/EnvPill.vue';
import PrimaryNav from './Partials/PrimaryNav.vue';
import ShellDrawer from './Partials/ShellDrawer.vue';
import UserMenu from './Partials/UserMenu.vue';
import WorkspaceSwitcher from './Partials/WorkspaceSwitcher.vue';

/*
 * The shell for pages under pages/Workspace: a header, never a sidebar. The product mark (the platform's logomark and name, never a workspace's) and a workspace switcher
 * (hidden for one workspace) sit at the left, then the workspace's pages with one active mark (Settings is one of them, a landing page), then the environment and the user menu on the right. Below `lg` a drawer holds the same items. The app
 * owns its mark (`mark` slot), what the account menu adds (`extra`), header controls (`actions`, in the header and the drawer), the footer and the width (`widthClass`); the
 * chrome comes from the server's `layout` props.
 */
const props = withDefaults(defineProps<{ layout: Tey.Kit.Features.SessionUi.Data.ShellData; widthClass?: string }>(), { widthClass: 'max-w-7xl' });
</script>
<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:ring-2 focus:ring-ring">Skip to content</a>
        <header v-if="props.layout.user" class="sticky top-0 z-30 border-b border-border bg-background/90 backdrop-blur" data-shell-bar>
            <div :class="['mx-auto flex h-16 items-center gap-2 px-4 sm:px-6 lg:px-8', props.widthClass]">
                <Link :href="props.layout.product.url" class="shrink-0 truncate rounded-md px-1 text-base font-semibold tracking-tight text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-shell-wordmark>
                    <slot name="mark"><span class="flex items-center gap-2"><img v-if="props.layout.product.logomark" :src="props.layout.product.logomark" alt="" class="size-6" />{{ props.layout.product.name }}</span></slot>
                </Link>
                <WorkspaceSwitcher v-if="props.layout.switcher.choices.length > 1" :switcher="props.layout.switcher" :all-url="props.layout.links.switcher" class="hidden lg:flex" />
                <div class="ml-3 hidden items-center gap-0.5 lg:flex">
                    <PrimaryNav :navigation="props.layout.navigation" :label="props.layout.switcher.current?.name ?? props.layout.product.name" />
                </div>
                <div class="ml-auto flex shrink-0 items-center gap-2">
                    <EnvPill v-if="props.layout.env" :env="props.layout.env" />
                    <div class="hidden items-center gap-2 lg:flex">
                        <slot name="actions" />
                        <UserMenu v-if="props.layout.user && props.layout.links.sign_out" :user="props.layout.user" :sign-out-url="props.layout.links.sign_out">
                            <template #extra><slot name="extra" /></template>
                        </UserMenu>
                    </div>
                    <div class="lg:hidden">
                        <ShellDrawer :layout="props.layout">
                            <template #actions><slot name="actions" /></template>
                            <template #extra><slot name="extra" /></template>
                        </ShellDrawer>
                    </div>
                </div>
            </div>
        </header>
        <main id="main" class="flex-1">
            <div :class="['mx-auto w-full px-4 pt-4 pb-8 sm:px-6 sm:pt-5 sm:pb-10 lg:px-8', props.widthClass]" data-shell-content>
                <Breadcrumbs />
                <slot />
            </div>
        </main>
        <slot name="footer" />
    </div>
</template>
