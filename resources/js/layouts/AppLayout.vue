<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import NavLinks from '@/components/Shell/NavLinks.vue';
import PhoneMenu from '@/components/Shell/PhoneMenu.vue';
import ShellBreadcrumbs from '@/components/Shell/ShellBreadcrumbs.vue';
import ShellFooter from '@/components/Shell/ShellFooter.vue';
import { shellFrom } from '@/components/Shell/types';
import UserMenu from '@/components/Shell/UserMenu.vue';
import Wordmark from '@/components/Shell/Wordmark.vue';
import { Toaster } from '@/components/ui/sonner';

/*
 * The signed-in shell: the app-shell recipe's header variant. The bar holds
 * the wordmark, the primary nav and the user menu (a sheet below `lg`); a
 * quiet footer sits under the content. Its chrome comes from the shared
 * `layout` prop (tey/kit's ShellData), decided on the server, and the shared
 * `breadcrumbs` only add breadcrumbs. The shell owns the gutters and the
 * measure, so pages add no outer padding.
 */
defineOptions({ inheritAttrs: false });

const page = usePage<{ layout?: Tey.Kit.Features.SessionUi.Data.ShellData; breadcrumbs?: { title: string; url: string }[]; title?: string }>();
const shell = computed(() => shellFrom(page.props.layout));
const crumbs = computed(() =>
    (page.props.breadcrumbs ?? []).map((crumb) => ({
        label: crumb.title,
        url: crumb.url,
    })),
);
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:ring-2 focus:ring-ring"
            >Skip to content</a
        >

        <header
            v-if="shell"
            class="sticky top-0 z-30 border-b border-border bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/75"
            data-shell-bar
        >
            <div
                class="mx-auto flex h-16 max-w-5xl items-center gap-2 px-4 sm:px-6 lg:px-8"
            >
                <Wordmark
                    :href="shell.homeUrl"
                    :product="shell.product"
                    data-shell-wordmark
                />
                <NavLinks
                    :navigation="shell.navigation"
                    class="ml-3 hidden lg:flex"
                />
                <div class="ml-auto flex shrink-0 items-center gap-2">
                    <div class="hidden lg:block">
                        <UserMenu
                            :user="shell.user"
                            :settings-url="shell.settingsUrl"
                            :sign-out-url="shell.signOutUrl"
                        />
                    </div>
                    <div class="lg:hidden">
                        <PhoneMenu
                            :navigation="shell.navigation"
                            :user="shell.user"
                            :settings-url="shell.settingsUrl"
                            :sign-out-url="shell.signOutUrl"
                        />
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            <div
                class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
                data-shell-content
            >
                <ShellBreadcrumbs :breadcrumbs="crumbs" />
                <h1 v-if="page.props.title" class="mb-6 font-serif text-4xl tracking-tight">{{ page.props.title }}</h1>
                <slot />
            </div>
        </main>

        <ShellFooter
            v-if="shell"
            :product="shell.product"
            :links="shell.secondaryLinks"
            :environment="shell.environment"
        />

        <Toaster />
    </div>
</template>
