<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Breadcrumbs from './Partials/Breadcrumbs.vue';
import EnvPill from './Partials/EnvPill.vue';
import PrimaryNav from './Partials/PrimaryNav.vue';
import SettingsMenu from './Partials/SettingsMenu.vue';
import ShellDrawer from './Partials/ShellDrawer.vue';
import UserMenu from './Partials/UserMenu.vue';
import WorkspaceSwitcher from './Partials/WorkspaceSwitcher.vue';

/*
 * The shell for pages under pages/Workspace: a header, never a sidebar. The product mark and a workspace switcher
 * (hidden for one workspace) sit at the left, then the workspace's pages with one active mark, then Settings as its
 * own menu, then the environment and the user menu on the right. Below `lg` a drawer holds the same items. The app
 * owns its mark (`mark` slot), what the account menu adds (`extra`), the footer and the width (`widthClass`); the
 * chrome comes from the server's `layout` props.
 */
const props = withDefaults(defineProps<{ layout: Tey.Kit.Features.SessionUi.Data.ShellData; widthClass?: string }>(), { widthClass: 'max-w-7xl' });
</script>
<template>
    <Head :title="props.layout.title" />
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:ring-2 focus:ring-ring">Skip to content</a>
        <header v-if="props.layout.user" class="sticky top-0 z-30 border-b border-border bg-background/90 backdrop-blur" data-shell-bar>
            <div :class="['mx-auto flex h-16 items-center gap-2 px-4 sm:px-6 lg:px-8', props.widthClass]">
                <Link :href="props.layout.product.url" class="shrink-0 truncate rounded-md px-1 text-base font-semibold tracking-tight text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-shell-wordmark>
                    <slot name="mark">{{ props.layout.product.name }}</slot>
                </Link>
                <WorkspaceSwitcher v-if="props.layout.switcher.choices.length > 1" :switcher="props.layout.switcher" :all-url="props.layout.links.switcher" class="hidden lg:flex" />
                <div class="ml-3 hidden items-center gap-0.5 lg:flex">
                    <PrimaryNav :navigation="props.layout.navigation" :label="props.layout.switcher.current?.name ?? props.layout.product.name" />
                    <SettingsMenu v-if="props.layout.settings" :settings="props.layout.settings" />
                </div>
                <div class="ml-auto flex shrink-0 items-center gap-2">
                    <EnvPill v-if="props.layout.env" :env="props.layout.env" />
                    <div class="hidden lg:block">
                        <UserMenu v-if="props.layout.user && props.layout.links.sign_out" :user="props.layout.user" :sign-out-url="props.layout.links.sign_out">
                            <template #extra><slot name="extra" /></template>
                        </UserMenu>
                    </div>
                    <div class="lg:hidden">
                        <ShellDrawer :layout="props.layout">
                            <template #extra><slot name="extra" /></template>
                        </ShellDrawer>
                    </div>
                </div>
            </div>
        </header>
        <main id="main" class="flex-1">
            <div :class="['mx-auto w-full px-4 py-8 sm:px-6 sm:py-10 lg:px-8', props.widthClass]" data-shell-content>
                <Breadcrumbs :breadcrumbs="props.layout.breadcrumbs" />
                <slot />
            </div>
        </main>
        <slot name="footer" />
    </div>
</template>
